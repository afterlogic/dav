<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\Auth\Backend;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Basic extends \Sabre\DAV\Auth\Backend\AbstractBasic
{

	/**
     * Validates a username and password
     *
     * This method should return true or false depending on if login
     * succeeded.
     *
     * @return bool
     */
    protected function validateUserPass($sUserName, $sPassword)
	{
		$mResult = false;
		if (class_exists('\\Aurora\\System\\Api') && \Aurora\System\Api::IsValid()) 
		{
			$mResult = \Afterlogic\DAV\Auth\Backend::Login($sUserName, $sPassword);

			$bIsMobileSync = false;
			$bIsOutlookSyncAllowed = false;
			$bIsDemo = false;

            if ($mResult !== false) 
            {

                $bIsOutlookSyncClient = \Afterlogic\DAV\Utils::ValidateClient('outlooksync');
                $oOutlookSyncWebclientModule = \Aurora\Api::GetModule('OutlookSyncWebclient');
                $bIsOutlookSyncAllowed = ($oOutlookSyncWebclientModule && !$oOutlookSyncWebclientModule->getConfig('Disabled', false));
                if ($bIsOutlookSyncClient && !$bIsOutlookSyncAllowed)
                {
                    $mResult = false;
                }


                $oMobileSyncModule = \Aurora\Api::GetModule('MobileSync');
                $bIsMobileSyncAllowed = ($oMobileSyncModule && !$oMobileSyncModule->getConfig('Disabled', false));

                if (!$bIsMobileSyncAllowed)
                {
                    $mResult = false;
                }
    

/*					
    			$iIdUser = isset($mResult['id']) ? $mResult['id'] : 0;

				$bIsMobileSync = $oApiCapabilityManager->isMobileSyncSupported($iIdUser);
				$bIsOutlookSync = $oApiCapabilityManager->isOutlookSyncSupported($iIdUser);

				\Aurora\System\Api::Plugin()->RunHook(
						'plugin-is-demo-account', 
						array(&$oAccount, &$bIsDemo)
				);
* 
*/
			}
/*
			if (($oAccount && $oAccount->IncomingMailPassword === $sPassword &&
					(($bIsMobileSync && !$bIsOutlookSyncClient) || 
					($bIsOutlookSync && $bIsOutlookSyncClient))) ||
					$bIsDemo || $sUserName === \Aurora\System\Api::ExecuteMethod('Dav::GetPublicUser')) {
				return true;
			}
 */
		}

		return $mResult;
	}
	
    /**
     * When this method is called, the backend must check if authentication was
     * successful.
     *
     * The returned value must be one of the following
     *
     * [true, "principals/username"]
     * [false, "reason for failure"]
     *
     * If authentication was successful, it's expected that the authentication
     * backend returns a so-called principal url.
     *
     * Examples of a principal url:
     *
     * principals/admin
     * principals/user1
     * principals/users/joe
     * principals/uid/123457
     *
     * If you don't use WebDAV ACL (RFC3744) we recommend that you simply
     * return a string such as:
     *
     * principals/users/[username]
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return array
     */
    public function check(\Sabre\HTTP\RequestInterface $request, \Sabre\HTTP\ResponseInterface $response) {

        $auth = new \Sabre\HTTP\Auth\Basic(
            $this->realm,
            $request,
            $response
        );

        $userpass = $auth->getCredentials($request);
        if (!$userpass) 
		{
            return [false, "No 'Authorization: Basic' header found. Either the client didn't send one, or the server is mis-configured"];
        }
        $mValidateResult = $this->validateUserPass($userpass[0], $userpass[1]);
		if (!$mValidateResult) 
		{
            return [false, "Username or password was incorrect"];
        }
		else
		{
			$mValidateResult = $userpass[0];
		}
		
        return [true, $this->principalPrefix . $mValidateResult];

    }	
}
