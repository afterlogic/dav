<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Auth;

class Plugin extends \Sabre\DAV\Auth\Plugin {
	
	public function getCurrentPrincipal()
	{
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			return \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $oUser->PublicId;
		}
	}
}
