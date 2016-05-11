<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV;

class Utils
{
	/* 
	 * @var $oUsersManager \CApiUsersManager 
	 */
	public static $oUsersManager = null;
	
	public static function getServer()
	{
		return \Afterlogic\DAV\Server::getInstance();
	}

	public static function getPlugins()
	{
		return self::getServer()->getPlugins();
	}	
	
	public static function getPlugin($sName)
	{
		return self::getServer()->getPlugin($sName);
	}	

	public static function getUsersManager()
	{
		if (null === self::$oUsersManager) {
			
			self::$oUsersManager = \CApi::GetCoreManager('users');
		}
		return self::$oUsersManager;
	}
	
	public static function getCurrentAccount()
	{
		return self::getServer()->getUser();
	}
	
	public static function getTenantUser($oAccount)
	{
		$sEmail = 'default_' . Constants::DAV_TENANT_PRINCIPAL;
		if ($oAccount->IdTenant > 0) {
			
			$oApiTenantsMan = \CApi::GetCoreManager('tenants');
			$oTenant = $oApiTenantsMan ? $oApiTenantsMan->getTenantById($oAccount->IdTenant) : null;
			if ($oTenant) {
				
				$sEmail = $oTenant->Login . '_' . Constants::DAV_TENANT_PRINCIPAL;
			}
		}
		
		return $sEmail;
	}
	
	public static function getTenantPrincipalUri($principalUri)
	{
		$sTenantPrincipalUri = null;
		
		$oAccount = self::GetAccountByLogin(basename($principalUri));
		if ($oAccount) {
			$aTenantEmail = self::getTenantUser($oAccount);
			$sTenantPrincipalUri = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $aTenantEmail;
		}
		
		return $sTenantPrincipalUri;
	}	
	
	public static function ValidateClient($sClient)
	{
		$bIsSync = false;
		if (isset($GLOBALS['server']) && $GLOBALS['server'] instanceof \Sabre\DAV\Server) {
			$aHeaders = $GLOBALS['server']->httpRequest->getHeaders();
			if (isset($aHeaders['user-agent'])) {
				$sUserAgent = $aHeaders['user-agent'];
				if (strpos(strtolower($sUserAgent), 'afterlogic ' . strtolower($sClient)) !== false) {
					$bIsSync = true;
				}
			}
		}
		return $bIsSync;
	}

	public static function GetAccountByLogin($sUserName)
	{
		return self::getUsersManager()->getAccountByEmail($sUserName);
	}	

	public static function getPrincipalByEmail($sEmail) 
	{
		$sEmail = trim(str_ireplace("mailto:", "", $sEmail));
		
		$aPrincipalsPath = Backend::Principal()->searchPrincipals(
				\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX, 
				array(
					'{http://sabredav.org/ns}email-address' => $sEmail
				)
		);
		if(is_array($aPrincipalsPath) && count($aPrincipalsPath) === 0) {
			$aPrincipalsPath = Backend::Principal()->searchPrincipals(
					\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX, 
					array(
						'{http://sabredav.org/ns}email-address' => $sEmail
					)
			);
			if(is_array($aPrincipalsPath) && count($aPrincipalsPath) === 0) {
				throw new \Exception("Unknown email address");
			}
		}
		
		$aPrincipals = array_filter(
				$aPrincipalsPath, 
				function ($sPrincipalPath) use ($sEmail) {
					return ($sPrincipalPath === \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sEmail);
				}
		);
		
		if (count($aPrincipals) === 0) {
			throw new \Exception("Unknown email address");
		}
		
		return Backend::Principal()->getPrincipalByPath($aPrincipals[0]);
	}
	
}