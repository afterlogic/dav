<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\PropertyStorage\Backend;

class PDO extends \Sabre\DAV\PropertyStorage\Backend\PDO
{
	/**
	 * Creates the backend
	 */
	public function __construct() 
	{
		parent::__construct(\Aurora\System\Api::GetPDO());
		
		$this->dBPrefix = \Aurora\System\Api::GetSettings()->GetConf('DBPrefix');
		$this->tableName = $this->dBPrefix.'adav_propertystorage';
	}
}
