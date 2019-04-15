<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Local\Personal;

class File extends \Afterlogic\DAV\FS\Local\File
{
	public function __construct($path) 
	{
		parent::__construct(\Aurora\System\Enums\FileStorageType::Personal, $path);
	}
	
	public function delete() 
	{
		$result = parent::delete();
		
		$this->updateUsedSpace();
				
		return $result;
	}
	
	public function put($data) 
	{
		$result = parent::put($data);

		$this->updateUsedSpace();

		return $result;
	}
}
