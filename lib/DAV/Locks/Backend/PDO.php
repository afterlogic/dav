<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Locks\Backend;

use Afterlogic\DAV\Constants;

class PDO extends \Sabre\DAV\Locks\Backend\PDO {

    /**
     * Constructor 
     */
    public function __construct() {

		parent::__construct(\Aurora\System\Api::GetPDO());
		
		$dbPrefix = \Aurora\System\Api::GetSettings()->GetConf('DBPrefix');
		$this->tableName = $dbPrefix.Constants::T_LOCKS;
    }
}
