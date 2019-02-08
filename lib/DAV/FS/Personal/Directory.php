<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Personal;

class Directory extends \Afterlogic\DAV\FS\Directory 
{
	use NodeTrait;
	
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