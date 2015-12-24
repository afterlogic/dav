<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Auth;

class Plugin extends \Sabre\DAV\Auth\Plugin {
	
	
	public function setCurrentPrincipal($path)
	{
		$this->currentPrincipal = $path;
	}
}
