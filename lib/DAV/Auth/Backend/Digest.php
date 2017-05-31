<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Auth\Backend;

class Digest extends \Sabre\DAV\Auth\Backend\AbstractDigest
{

	public function getDigestHash($sRealm, $sUserName)
	{
		if (class_exists('CApi') && \Aurora\System\Api::IsValid()) {
			
			if (true) {
				
				$oAccount = \Afterlogic\DAV\Utils::GetAccountByLogin($sUserName);
				if ($oAccount && $oAccount->IsDisabled) {
					
					return null;
				}

				$bIsOutlookSyncClient = \Afterlogic\DAV\Utils::ValidateClient('outlooksync');

				$bIsMobileSync = false;
				$bIsOutlookSync = false;
				$bIsDemo = false;

				if ($oAccount) {
					
					$bIsMobileSync = /*$oApiCapabilityManager->isMobileSyncSupported($oAccount)*/ true; // TODO
					$bIsOutlookSync = /*$oApiCapabilityManager->isOutlookSyncSupported($oAccount)*/ true; // TODO
					
					\Aurora\System\Api::Plugin()->RunHook(
							'plugin-is-demo-account', 
							array(&$oAccount, &$bIsDemo)
					);
				}
				if (($oAccount && (($bIsMobileSync && !$bIsOutlookSyncClient) || 
						($bIsOutlookSync && $bIsOutlookSyncClient))) ||
						$bIsDemo || $sUserName === \Aurora\System\Api::ExecuteMethod('Dav::GetPublicUser')) {
					
					return md5($sUserName.':'.$sRealm.':'.($bIsDemo ? 'demo' : $oAccount->IncomingMailPassword));
				}
			}
		}

		return null;
	}
}
