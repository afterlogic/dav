<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Corporate;

class File extends \Afterlogic\DAV\FS\File
{
 	public function __construct($path) 
	{
		parent::__construct(\Aurora\System\Enums\FileStorageType::Corporate, $path);
	}
}

