<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Auth\Backend;

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

			$bIsOutlookSyncClient = \Afterlogic\DAV\Utils::ValidateClient('outlooksync');

			$bIsMobileSync = false;
			$bIsOutlookSync = false;
			$bIsDemo = false;

//				if ($mResult !== false) {

//					$iIdUser = isset($mResult['id']) ? $mResult['id'] : 0;

//					return true;
/*					
				$bIsMobileSync = $oApiCapabilityManager->isMobileSyncSupported($iIdUser);
				$bIsOutlookSync = $oApiCapabilityManager->isOutlookSyncSupported($iIdUser);

				\Aurora\System\Api::Plugin()->RunHook(
						'plugin-is-demo-account', 
						array(&$oAccount, &$bIsDemo)
				);
* 
*/
//				}
/*
			if (($oAccount && $oAccount->IncomingMailPassword === $sPassword &&
					(($bIsMobileSync && !$bIsOutlookSyncClient) || 
					($bIsOutlookSync && $bIsOutlookSyncClient))) ||
					$bIsDemo || $sUserName === \Aurora\System\Api::ExecuteMethod('Dav::GetPublicUser')) {
				return true;
			}
 * 
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
        return [true, $this->principalPrefix . $mValidateResult];

    }	
}
