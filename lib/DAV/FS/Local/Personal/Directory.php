<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Local\Personal;

use Afterlogic\DAV\FS\HistoryDirectory;
use Afterlogic\DAV\Server;
use Sabre\DAV\Exception\NotFound;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Directory extends \Afterlogic\DAV\FS\Local\Directory 
{
	use \Afterlogic\DAV\FS\Shared\DirectoryTrait;

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

		if (!$mResult) {
			throw new NotFound();
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
	
	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = []) 
	{
		$result = parent::createFile($name, $data, $rangeType, $offset, $extendedProps);

		$this->updateUsedSpace();
		
		return $result;
	}
	
	function getQuotaInfo() {
		$oRoot = Server::getNodeForPath('files/personal');
		if ($oRoot) {
			return $oRoot->getQuotaInfo();
		} else {
			return [0, 0];
		}
	}

	public function getChildren($sPattern = null)
	{
		return array_merge(
			parent::getChildren($sPattern),
			$this->getSharedChildren()
		);
	}
}
