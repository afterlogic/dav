<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Auth;

class Backend
{
	protected static $instance;
	
	public static function getInstance()
	{
        if(null === self::$instance) {
			
            self::$instance = (\Aurora\System\Api::GetConf('labs.dav.use-digest-auth', false)) 
					? new Backend\Digest() : new Backend\Basic();
        }
        return self::$instance;		
	}
	
	public static function Login($sUserName, $sPassword)
	{
		$mResult = false;
		$aArguments = array(
			'Login' => $sUserName,
			'Password' => $sPassword,
			'SignMe' => false

		);		
		\Aurora\System\Api::GetModuleManager()->broadcastEvent(
			'Dav', 
			'Login', 
			$aArguments, 
			$mResult
		);
		if (isset($mResult['id']))
		{
			$oManagerApi = \Aurora\System\Api::GetSystemManager('eav', 'db');
			$oEntity = $oManagerApi->getEntity((int) $mResult['id']);
			$mResult = $oEntity->sUUID;
		}
		else 
		{
			$mResult = false;
		}
		
		return $mResult;
	}
}