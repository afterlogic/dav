<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\Auth;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
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
	
	/**
	 * 
	 */
	public static function Login($sUserName, $sPassword)
	{
		$mResult = false;
		
		$oDavModule = \Aurora\System\Api::GetModuleDecorator('Dav');
		if ($oDavModule)
		{
			try
			{
				$mResult = $oDavModule->Login($sUserName, $sPassword);

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
