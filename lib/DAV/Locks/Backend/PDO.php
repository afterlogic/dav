<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Locks\Backend;

use Afterlogic\DAV\Constants;

class PDO extends \Sabre\DAV\Locks\Backend\PDO {

    /**
     * Constructor 
     */
    public function __construct() {

		parent::__construct(\CApi::GetPDO());
		
		$dbPrefix = \CApi::GetSettings()->GetConf('Common/DBPrefix');
		$this->tableName = $dbPrefix.Constants::T_LOCKS;
    }
}
