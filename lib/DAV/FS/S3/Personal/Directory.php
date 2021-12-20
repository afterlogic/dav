<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\S3\Personal;

use Afterlogic\DAV\Constants;
use Afterlogic\DAV\FS\Backend\PDO;
use Afterlogic\DAV\FS\Shared\Root;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Directory extends \Afterlogic\DAV\FS\S3\Directory
{
	protected $storage = 'personal';

	public function getChildren($sPattern = null)
	{
		return array_merge(
			parent::getChildren(),
			$this->getSharedChildren()
		);
	}

	public function getSharedChildren()
	{
		$aChildren = [];
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
			$aChildren[] =  \Afterlogic\DAV\FS\Shared\Root::populateItem($aSharedFile);
		}

		return $aChildren;
	}

	public function getChild($name)
	{	
		$mResult = false;
		try {
			$mResult = parent::getChild($name);
		} catch (\Exception $oEx) {}

		
		if (!$mResult) {
			$mResult = $this->getSharedChild($name);
		}

		return $mResult;
	}

	public function getSharedChild($name)
	{
		$oPdo = new PDO();

		$sSharePath = '';
		if (!empty($this->getRelativePath())) {
			$sSharePath = $this->getRelativePath() . '/' . $this->getName();
		} else if (!empty($this->getName()) && !$this->isRoot()) {
			$sSharePath = '/' . $this->getName();
		}
		$aSharedFile = $oPdo->getSharedFileByUid(Constants::PRINCIPALS_PREFIX . $this->getUser(), $name, $sSharePath);

		return Root::populateItem($aSharedFile);
	}
}
