<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Auth;

class Backend
{
	protected static $instance;
	
	public static function getInstance()
	{
        if (null === self::$instance)
		{
			$oDavModule = \Aurora\System\Api::GetModule('Dav'); 
            self::$instance = ($oDavModule->getConfig('UseDigestAuth', false)) 
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
			$oEavManager = new \Aurora\System\Managers\Eav\Manager();
			$oEntity = $oEavManager->getEntity((int) $mResult['id']);
			$mResult = $oEntity->UUID;
		}
		else 
		{
			$mResult = false;
		}
		
		return $mResult;
	}
}