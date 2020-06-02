<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Utils
{
	/*
	 * @var $oUsersManager \CApiUsersManager
	 */
	public static $oUsersManager = null;

	public static function getUsersManager()
	{
		if (null === self::$oUsersManager) {

			self::$oUsersManager = \Aurora\System\Api::GetSystemManager('users');
		}
		return self::$oUsersManager;
	}

	public static function getCurrentAccount()
	{
		return \Afterlogic\DAV\Server::getUser();
	}

	public static function getTenantUser($oAccount)
	{
		$sEmail = 'default_' . Constants::DAV_TENANT_PRINCIPAL;
		if ($oAccount->IdTenant > 0) {

			$oApiTenantsMan = \Aurora\System\Api::GetSystemManager('tenants');
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
			$sTenantPrincipalUri = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $aTenantEmail;
		}

		return $sTenantPrincipalUri;
	}

	public static function ValidateClient($sClient)
	{
		$bIsSync = false;

		$oServer = \Afterlogic\DAV\Server::getInstance();

		$sUserAgent = $oServer->httpRequest->getHeader('user-agent');
		if (isset($sUserAgent) && strpos(strtolower($sUserAgent), 'afterlogic ' . strtolower($sClient)) !== false) {
			$bIsSync = true;
		}

		return $bIsSync;
	}
	//GetAccountByLogin
	public static function GetUserByPublicId($sUserName)
	{
		$bPrevState =  \Aurora\System\Api::skipCheckUserRole(true);
		$mResult = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserName);
		\Aurora\System\Api::skipCheckUserRole($bPrevState);

		return $mResult;
	}

	public static function getPrincipalByEmail($sEmail)
	{
		$sEmail = trim(str_ireplace("mailto:", "", $sEmail));

		$aPrincipalsPath = Backend::Principal()->searchPrincipals(
				\rtrim(Constants::PRINCIPALS_PREFIX, '/'),
				array(
					'{http://sabredav.org/ns}email-address' => $sEmail
				)
		);
		if(is_array($aPrincipalsPath) && count($aPrincipalsPath) === 0) {
			$aPrincipalsPath = Backend::Principal()->searchPrincipals(
					\rtrim(Constants::PRINCIPALS_PREFIX, '/'),
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
					return ($sPrincipalPath === \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $sEmail);
				}
		);

		if (count($aPrincipals) === 0) {
			throw new \Exception("Unknown email address");
		}

		return Backend::Principal()->getPrincipalByPath($aPrincipals[0]);
	}

}
