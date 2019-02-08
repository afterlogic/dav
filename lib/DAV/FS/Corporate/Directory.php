<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Corporate;

class Directory extends \Afterlogic\DAV\FS\Directory {
    
	public function __construct($path) 
	{
		parent::__construct(\Aurora\System\Enums\FileStorageType::Corporate, $path);
	}

	public function getChild($name) 
    {
		$path = $this->checkFileName($name);

		return is_dir($path) ? new self($path) : new File($path);
	}		
	
    function getQuotaInfo() { }	
}