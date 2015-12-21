<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV;

class Utils
{
	public static $oUsersManager = null;
	
	public static function getUsersManager()
	{
		if (null === self::$oUsersManager)
		{
			/* @var $oUsersManager \CApiUsersManager */
			self::$oUsersManager = \CApi::GetCoreManager('users');
		}
		return self::$oUsersManager;
	}
	
	public static function getCurrentAccount()
	{
		return self::getUsersManager()->getAccountByEmail(\Afterlogic\DAV\Auth\Backend::getInstance()->getCurrentUser());
	}
	
	public static function getTenantUser($oAccount)
	{
		$sEmail = 'default_' . Constants::DAV_TENANT_PRINCIPAL;
		if ($oAccount->IdTenant > 0)
		{
			$oApiTenantsMan = \CApi::GetCoreManager('tenants');
			$oTenant = $oApiTenantsMan ? $oApiTenantsMan->getTenantById($oAccount->IdTenant) : null;
			if ($oTenant)
			{
				$sEmail = $oTenant->Login . '_' . Constants::DAV_TENANT_PRINCIPAL;
			}
		}
		
		return Backend::Principal()->getPrincipalByEmail($sEmail);
	}
	
	public static function getTenantPrincipalUri($principalUri)
	{
		$sTenantPrincipalUri = null;
		
		$oAccount = \Afterlogic\DAV\Utils::GetAccountByLogin(basename($principalUri));
		if ($oAccount)
		{
			$sTenantEmail = self::getTenantUser($oAccount);
			$sTenantPrincipalUri = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sTenantEmail;
		}
		
		return $sTenantPrincipalUri;
	}	
	
	public static function ValidateClient($sClient)
	{
		$bIsSync = false;
		if (isset($GLOBALS['server']) && $GLOBALS['server'] instanceof \Sabre\DAV\Server)
		{
			$aHeaders = $GLOBALS['server']->httpRequest->getHeaders();
			if (isset($aHeaders['user-agent']))
			{
				$sUserAgent = $aHeaders['user-agent'];
				if (strpos(strtolower($sUserAgent), 'afterlogic ' . strtolower($sClient)) !== false)
				{
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

	public static function CheckPrincipals($sUserName)
	{
		if (trim($sUserName) !== '')
		{
			$oPdo = \CApi::GetPDO();
			$dbPrefix = \CApi::GetSettingsConf('Common/DBPrefix');
			$oAccount = self::GetAccountByLogin($sUserName);
			if ($oAccount)
			{
				$sPrincipal = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sUserName;

				$oStmt = $oPdo->prepare(
					'SELECT id FROM '.$dbPrefix.Constants::T_PRINCIPALS.' WHERE uri = ? LIMIT 1'
				);
				$oStmt->execute(array($sPrincipal));
				if(count($oStmt->fetchAll()) === 0)
				{
					$oStmt = $oPdo->prepare(
						'INSERT INTO '.$dbPrefix.Constants::T_PRINCIPALS.'
							(uri,email,displayname) VALUES (?, ?, ?)'
					);
					try
					{
						$oStmt->execute(array($sPrincipal, $sUserName, ''));
					}
					catch (Exception $e){}
				}

				$oStmt = $oPdo->prepare(
					'SELECT principaluri FROM '.$dbPrefix.Constants::T_CALENDARS.'
						WHERE principaluri = ?'
				);
				$oStmt->execute(array($sPrincipal));
				if (count($oStmt->fetchAll()) === 0)
				{
					$oStmt = $oPdo->prepare(
						'INSERT INTO '.$dbPrefix.Constants::T_CALENDARS.'
							(principaluri, displayname, uri, description, components, ctag, calendarcolor)
							VALUES (?, ?, ?, ?, ?, 1, ?)'
					);

					$oStmt->execute(array(
							$sPrincipal,
							\CApi::ClientI18N('CALENDAR/CALENDAR_DEFAULT_NAME', $oAccount),
							\Sabre\DAV\UUIDUtil::getUUID(),
							'',
							'VEVENT,VTODO',
							Constants::CALENDAR_DEFAULT_COLOR
						)
					);
				}		

				$oStmt = $oPdo->prepare(
					'SELECT principaluri FROM '.$dbPrefix.Constants::T_CALENDARS.'
						WHERE principaluri = ? and uri = ? LIMIT 1'
				);
				$oStmt->execute(array($sPrincipal, Constants::CALENDAR_DEFAULT_NAME));
				if (count($oStmt->fetchAll()) !== 0)
				{
					$oStmt = $oPdo->prepare(
						'UPDATE '.$dbPrefix.Constants::T_CALENDARS.'
							SET uri = ? WHERE principaluri = ? and uri = ?'
					);
					$oStmt->execute(array(
							\Sabre\DAV\UUIDUtil::getUUID(),
							$sPrincipal,
							Constants::CALENDAR_DEFAULT_NAME
						)
					);
				}

				$oStmt = $oPdo->prepare(
					'SELECT principaluri FROM '.$dbPrefix.Constants::T_ADDRESSBOOKS.'
						WHERE principaluri = ? and uri = ? LIMIT 1'
				);

				$oStmt->execute(array($sPrincipal, Constants::ADDRESSBOOK_DEFAULT_NAME));
				$bHasDefaultAddressbooks = (count($oStmt->fetchAll()) != 0);

				$oStmt->execute(array($sPrincipal, Constants::ADDRESSBOOK_DEFAULT_NAME_OLD));
				$bHasOldDefaultAddressbooks = (count($oStmt->fetchAll()) != 0);

				$oStmt->execute(array($sPrincipal, Constants::ADDRESSBOOK_COLLECTED_NAME));
				$bHasCollectedAddressbooks = (count($oStmt->fetchAll()) != 0);

				$stmt1 = $oPdo->prepare(
					'INSERT INTO '.$dbPrefix.Constants::T_ADDRESSBOOKS.'
						(principaluri, displayname, uri, description, ctag)
						VALUES (?, ?, ?, ?, 1)'
				);
				if (!$bHasDefaultAddressbooks)
				{
					if ($bHasOldDefaultAddressbooks)
					{
						$oStmt = $oPdo->prepare(
							'UPDATE '.$dbPrefix.Constants::T_ADDRESSBOOKS.'
								SET uri = ? WHERE principaluri = ? and uri = ?'
						);
						$oStmt->execute(array(
								Constants::ADDRESSBOOK_DEFAULT_NAME,
								$sPrincipal,
								Constants::ADDRESSBOOK_DEFAULT_NAME_OLD,
							)
						);
					}
					else
					{
						$stmt1->execute(array(
								$sPrincipal,
								Constants::ADDRESSBOOK_DEFAULT_DISPLAY_NAME,
								Constants::ADDRESSBOOK_DEFAULT_NAME,
								Constants::ADDRESSBOOK_DEFAULT_DISPLAY_NAME
							)
						);
					}
				}
				if (!$bHasCollectedAddressbooks)
				{
					$stmt1->execute(array(
							$sPrincipal,
							Constants::ADDRESSBOOK_COLLECTED_DISPLAY_NAME,
							Constants::ADDRESSBOOK_COLLECTED_NAME,
							Constants::ADDRESSBOOK_COLLECTED_DISPLAY_NAME
						)
					);
				}
			}
		}
	}
	
	public static function getPrincipalByEmail($sEmail) 
	{
		$sEmail = trim(str_ireplace("mailto:", "", $sEmail));
		
		$oPrincipalBackend = Backend::Principal();
		$mPrincipalPath = $oPrincipalBackend->searchPrincipals(\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX, array('{http://sabredav.org/ns}email-address'=>$sEmail));
		if(is_array($mPrincipalPath) && count($mPrincipalPath) === 0) 
		{
			\Afterlogic\DAV\Utils::CheckPrincipals($sEmail);
			$mPrincipalPath = $oPrincipalBackend->searchPrincipals(\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX, array('{http://sabredav.org/ns}email-address'=>$sEmail));
			if(is_array($mPrincipalPath) && count($mPrincipalPath) === 0) 
			{
				throw new \Exception("Unknown email address");
			}
		}
		
		$sPrincipal = null;
		foreach ($mPrincipalPath as $aPrincipal)
		{
			if ($aPrincipal === \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sEmail)
			{
				$sPrincipal = $aPrincipal;
				break;
			}
		}
		if (!isset($sPrincipal))
		{
			throw new \Exception("Unknown email address");
		}
		
		return $oPrincipalBackend->getPrincipalByPath($sPrincipal);
	}
	
}