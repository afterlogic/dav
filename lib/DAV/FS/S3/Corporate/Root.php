<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\S3\Corporate;

class Root extends \Afterlogic\DAV\FS\S3\Personal\Root 
{
	public function __construct($sUser = null)
	{
		parent::__construct('corporate');
	}
	
	public function getName() 
	{
        return \Aurora\System\Enums\FileStorageType::Corporate;
	}
}