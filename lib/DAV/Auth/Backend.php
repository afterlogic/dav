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
			try
			{
				$mResult = $oCoreModule->Login($sUserName, $sPassword, true);

				if (isset($mResult['AuthToken']))
				{
						$oUser = \Aurora\System\Api::getAuthenticatedUser($mResult['AuthToken']);
						if ($oUser)
						{
							$mResult = $oUser->PublicId;
						}
				}
				else 
				{
						$mResult = false;
				}
			} 
			catch (\Exception $ex) 
			{
					$mResult = false;
			}
		}
		
		return $mResult;
	}

}