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
		
		$oCoreModule = \Aurora\System\Api::GetModuleDecorator('Core');
		if ($oCoreModule)
		{
			$mResult = $oCoreModule->Login($sUserName, $sPassword);
		}
		
		if (isset($mResult['id']))
		{
			\Aurora\System\Api::setUserId((int) $mResult['id']);

			$oEavManager = new \Aurora\System\Managers\Eav();
			$oEntity = $oEavManager->getEntity((int) $mResult['id'], '\Aurora\Modules\Core\Classes\User');
			$mResult = $oEntity->PublicId;
		}
		else 
		{
			$mResult = false;
		}
		
		return $mResult;
	}
}