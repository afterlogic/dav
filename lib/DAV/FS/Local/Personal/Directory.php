<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Local\Personal;

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
		$path = $this->checkFileName($name);

		return is_dir($path) ? new self($path) : new File($path);
    }	
	
	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = []) 
	{
		$result = parent::createFile($name, $data, $rangeType, $offset, $extendedProps);

		$this->updateUsedSpace();
		
		return $result;
	}
	
	function getQuotaInfo() {}	
}
