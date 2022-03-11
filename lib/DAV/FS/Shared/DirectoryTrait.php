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
		}

		return $oChild;
	}

	protected function populateAccess(&$oChild, $aSharedFile) 
	{
		// NoAccess = 0;
		// Write	 = 1;
		// Read   = 2;
		// Reshare = 3;

		if ((int) $aSharedFile['group_id'] === 0) {
			
			$oChild->setAccess($aSharedFile['access']);
		} else {

			$iAccess = $oChild->getAccess();
			if ($aSharedFile['access'] !== Access::Read) {

				if ($iAccess < $aSharedFile['access'] ) {
					
					$oChild->setAccess($aSharedFile['access']);
				}
			} elseif ($iAccess !== Access::Write || $iAccess !== Access::Reshare) {
					
				$oChild->setAccess($aSharedFile['access']);
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
						$aChildren[] = $oChild;
					} else {
						$oPdo->deleteShare(
							Constants::PRINCIPALS_PREFIX . $this->getUser(), 
							$aSharedFile['uid'], 
							$sPath
						);
					}
				}
			}
		}

		return $aChildren;
	}

}