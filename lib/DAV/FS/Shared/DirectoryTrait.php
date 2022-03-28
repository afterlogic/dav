<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Shared;

use Afterlogic\DAV\Constants;
use Afterlogic\DAV\FS\Backend\PDO;
use Aurora\Modules\SharedFiles\Enums\Access;
use Aurora\System\Api;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
trait DirectoryTrait
{
	public function getSharedChild($name)
	{
		$oChild = false;

		$SharedFiles = Api::GetModule('SharedFiles');
		if ($SharedFiles && !$SharedFiles->getConfig('Disabled', false)) {
			$oPdo = new PDO();

			$sSharePath = '';
			if (!empty($this->getRelativePath())) {
				$sSharePath = $this->getRelativePath() . '/' . $this->getName();
			} else if (!empty($this->getName()) && !$this->isRoot()) {
				$sSharePath = '/' . $this->getName();
			}
			$aSharedFiles = $oPdo->getSharedFilesByUid(
                Constants::PRINCIPALS_PREFIX . $this->getUser(), 
                $name, $sSharePath
            );

			foreach ($aSharedFiles as $aSharedFile) {
				if ($aSharedFile['owner'] !== $aSharedFile['principaluri']) {

					if ($oChild) {
						$sPath = '/' . ltrim($oChild->getRelativePath() . '/' . $oChild->getName(), '/');
						if ($aSharedFile['path'] === $sPath) {

							$this->populateAccess($oChild, $aSharedFile);
						}
					} else {
						$oChild = Root::populateItem($aSharedFile);
					}
				}
			}

			if ($oChild && $oChild->getAccess() === Access::NoAccess) {
				$oChild = false;
			}
		}

		return $oChild;
	}

	protected function populateAccess(&$oChild, $aSharedFile) 
	{
		// NoAccess = 0;
		// Write	 = 1;
		// Read   = 2;
		// Reshare = 3;

		$iAccess = $oChild->getAccess();

		$sNewInitiator = $aSharedFile['initiator'];
		$iNewAccess = $aSharedFile['access'];
		if ((int) $aSharedFile['group_id'] === 0) { //personal sharing
			
			$oChild->setAccess($iNewAccess);
			$oChild->setInitiator($sNewInitiator);
			$oChild->setGroupId(0);
			
		} else { // group sharing

			if ($iNewAccess !== Access::Read) {

				if ($iAccess < $iNewAccess || $iNewAccess === Access::NoAccess) {
					
					$oChild->setAccess($iNewAccess);
					$oChild->setInitiator($sNewInitiator);
				}
			} elseif ($iAccess !== Access::Write && $iAccess !== Access::Reshare) {
					
				$oChild->setAccess($iNewAccess);
				$oChild->setInitiator($sNewInitiator);
			}
		}
	}

	public function getSharedChildren()
	{
		$aChildren = [];

		$SharedFiles = Api::GetModule('SharedFiles');
		if ($SharedFiles && !$SharedFiles->getConfig('Disabled', false)) {
			$oPdo = new PDO();

			$sPath = '';
			$bIsRoot = $this->getRootPath() === $this->getPath();
			if (!$bIsRoot) {
				$sPath = $this->getRelativePath();
				if (!empty($sPath))	{
					$sPath = '/' . ltrim($sPath, '/') . '/' . $this->getName();
				} else {
					$sPath = '/' . $this->getName();
				}
			}
			$aSharedFiles = $oPdo->getSharedFilesForUser(
				Constants::PRINCIPALS_PREFIX . $this->getUser(), 
				$sPath
			);

			foreach ($aSharedFiles as $aSharedFile) {
				if ($aSharedFile['owner'] !== $aSharedFile['principaluri']) {
					$bContinue= false;
					foreach ($aChildren as $oChild) {
						$sPath = '/' . ltrim($oChild->getRelativePath() . '/' . $oChild->getName(), '/');
						if ($aSharedFile['path'] === $sPath) {

							$this->populateAccess($oChild, $aSharedFile);

							$bContinue = true;
							break;
						}
					}

					if ($bContinue) {
						continue;
					}

					$oChild = Root::populateItem($aSharedFile);
					if ($oChild && $oChild->getNode() instanceof \Sabre\DAV\FS\Node) {
						if ($oChild->getAccess() !== Access::NoAccess) {
							$aChildren[] = $oChild;
						}
					} else {
						$oPdo->deleteShare(
							Constants::PRINCIPALS_PREFIX . $this->getUser(), 
							$aSharedFile['uid'], 
							$sPath
						);
					}
				}
			}

			$aChildren = array_filter(array_map(function ($oChild) {
				if ($oChild->getAccess() !== Access::NoAccess) {
					return $oChild;
				}
			}, $aChildren));
		}

		return $aChildren;
	}

}