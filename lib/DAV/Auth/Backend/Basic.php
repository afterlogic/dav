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
		if (class_exists('\\Aurora\\System\\Api') && \Aurora\System\Api::IsValid() && $sUserName !== \Afterlogic\DAV\Constants::DAV_PUBLIC_PRINCIPAL && $sUserName !== \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL)
		{
			$mResult = \Afterlogic\DAV\Auth\Backend::Login($sUserName, $sPassword);

			$bIsMobileSync = false;
			$bIsOutlookSyncAllowed = false;
			$bIsDemo = false;

            if ($mResult !== false)
            {

                $mResult = true;

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

}
