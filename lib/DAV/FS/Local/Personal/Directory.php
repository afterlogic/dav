<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Local\Personal;

use Afterlogic\DAV\Constants;
use \Afterlogic\DAV\FS\Backend\PDO;
use Afterlogic\DAV\FS\HistoryDirectory;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Directory extends \Afterlogic\DAV\FS\Local\Directory 
{
	public function __construct($path) 
	{
		parent::__construct(\Aurora\System\Enums\FileStorageType::Personal, $path);
	}
    
	public function getChild($name) 
    {
		$mResult = false;
		try {
			$mResult = $this->getLocalChild($name);
		} catch (\Exception $oEx) {}

		
		if (!$mResult) {
			$mResult = $this->getSharedChild($name);
		}

		return $mResult;
    }

	public function getLocalChild($name)
	{
		$result = null;

		$path = $this->checkFileName($name);

		if (is_dir($path)) {
			$ext = strtolower(substr($name, -5));
			if ($ext === '.hist') {
				$result = new HistoryDirectory($this->getStorage(), $path);
			} else {
				$result = new self($path);
			}
		} else {
			$result = new File($path);
		}

		return $result;
    }
	
	public function getChildren()
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
	
	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = []) 
	{
		$result = parent::createFile($name, $data, $rangeType, $offset, $extendedProps);

		$this->updateUsedSpace();
		
		return $result;
	}
	
	function getQuotaInfo() {}	
}
