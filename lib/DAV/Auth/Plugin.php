<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Auth;

class Plugin extends \Sabre\DAV\Auth\Plugin {
	
	public function getCurrentPrincipal()
	{
		return \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . \Afterlogic\DAV\Server::getUser();
	}
}
