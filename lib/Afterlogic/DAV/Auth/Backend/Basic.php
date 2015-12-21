<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Auth\Backend;

class Basic extends \Sabre\DAV\Auth\Backend\AbstractBasic
{
    /**
     * Creates the backend object.
     *
     * @return void
     */
    public function __construct()
	{
    }
	
    public function setCurrentUser($user)
	{
		$this->currentUser = $user;
	}
	
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
		if (class_exists('CApi') && \CApi::IsValid())
		{
			/* @var $oApiCapabilityManager \CApiCapabilityManager */
			$oApiCapabilityManager = \CApi::GetCoreManager('capability');

			if ($oApiCapabilityManager)
			{
				$oAccount = \Afterlogic\DAV\Utils::GetAccountByLogin($sUserName);
				if ($oAccount && $oAccount->IsDisabled)
				{
					return false;
				}

				$bIsOutlookSyncClient = \Afterlogic\DAV\Utils::ValidateClient('outlooksync');

				$bIsMobileSync = false;
				$bIsOutlookSync = false;
				$bIsDemo = false;

				if ($oAccount)
				{
					$bIsMobileSync = $oApiCapabilityManager->isMobileSyncSupported($oAccount);
					$bIsOutlookSync = $oApiCapabilityManager->isOutlookSyncSupported($oAccount);
					
					\CApi::Plugin()->RunHook('plugin-is-demo-account', array(&$oAccount, &$bIsDemo));
				}

				if (($oAccount && $oAccount->IncomingMailPassword === $sPassword &&
						(($bIsMobileSync && !$bIsOutlookSyncClient) || ($bIsOutlookSync && $bIsOutlookSyncClient))) ||
					$bIsDemo || ($sUserName === \CApi::ExecuteMethod('Dav::GetPublicUser'))
				)
				{
					\Afterlogic\DAV\Utils::CheckPrincipals($sUserName);
					
					return true;
				}
			}
		}

		return false;
	}
}
