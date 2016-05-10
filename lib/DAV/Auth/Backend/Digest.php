<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Auth\Backend;

class Digest extends \Sabre\DAV\Auth\Backend\AbstractDigest
{

	public function getDigestHash($sRealm, $sUserName)
	{
		if (class_exists('CApi') && \CApi::IsValid()) {
			
			/* @var $oApiCapabilityManager \CApiCapabilityManager */
			$oApiCapabilityManager = \CApi::GetCoreManager('capability');

			if ($oApiCapabilityManager) {
				
				$oAccount = \Afterlogic\DAV\Utils::GetAccountByLogin($sUserName);
				if ($oAccount && $oAccount->IsDisabled) {
					
					return null;
				}

				$bIsOutlookSyncClient = \Afterlogic\DAV\Utils::ValidateClient('outlooksync');

				$bIsMobileSync = false;
				$bIsOutlookSync = false;
				$bIsDemo = false;

				if ($oAccount) {
					
					$bIsMobileSync = $oApiCapabilityManager->isMobileSyncSupported($oAccount);
					$bIsOutlookSync = $oApiCapabilityManager->isOutlookSyncSupported($oAccount);
					
					\CApi::Plugin()->RunHook(
							'plugin-is-demo-account', 
							array(&$oAccount, &$bIsDemo)
					);
				}
				
				if (($oAccount && (($bIsMobileSync && !$bIsOutlookSyncClient) || 
						($bIsOutlookSync && $bIsOutlookSyncClient))) ||
						$bIsDemo || $sUserName === \CApi::ExecuteMethod('Dav::GetPublicUser')) {
					
					return md5($sUserName.':'.$sRealm.':'.($bIsDemo ? 'demo' : $oAccount->IncomingMailPassword));
				}
			}
		}

		return null;
	}
}