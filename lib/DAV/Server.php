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
class Server extends \Sabre\DAV\Server
{
	public static $sUserPublicId = null;

	public static $oUser = null;

	public static $oTenant = null;

	public $rootNode = null;

	/**
	 * @return \Afterlogic\DAV\Server
	 */
	static public function createInstance()
	{
		return new self();
	}

	/**
	 * @return \Afterlogic\DAV\Server
	 */
	static public function getInstance()
	{
		static $oInstance = null;
		if(is_null($oInstance))
		{
			$oInstance = new self();
		}
		return $oInstance;
	}

	public function __invoke()
	{
		return self::getInstance();
	}

	protected function isModuleEnabled($sModule)
	{
		return !\Aurora\System\Api::GetModuleManager()->getModuleConfigValue($sModule, 'Disabled', false);
	}

	protected function initServer()
	{
		/* Initializing server */
		$oTree = new Tree($this->rootNode);
 		parent::__construct($oTree);

		$this->debugExceptions = true;
		self::$exposeVersion = false;

		$this->httpResponse->setHeader("X-Server", Constants::DAV_SERVER_NAME);
		$this->httpResponse->setHeader('Cache-Control', 'no-cache');

		/* Authentication Plugin */
		$oAuthPlugin = 	new \Afterlogic\DAV\Auth\Plugin(new \Afterlogic\DAV\Auth\Backend\Basic());

		if (\Aurora\System\Api::GetModuleManager()->getModuleConfigValue('Dav', 'UseDigestAuth', false))
		{
			$oAuthPlugin->addBackend(new \Afterlogic\DAV\Auth\Backend\Digest());
		}
		$this->addPlugin($oAuthPlugin);

		/* DAV ACL Plugin */
		$aclPlugin = new \Sabre\DAVACL\Plugin();
		$aclPlugin->hideNodesFromListings = false;
		$aclPlugin->allowUnauthenticatedAccess = false;
		$aclPlugin->defaultUsernamePath = \rtrim(Constants::PRINCIPALS_PREFIX, '/');

		$mAdminPrincipal = \Aurora\System\Api::GetModuleManager()->getModuleConfigValue('Dav', 'AdminPrincipal', false);
		$aclPlugin->adminPrincipals = ($mAdminPrincipal !== false) ?
						[Constants::PRINCIPALS_PREFIX . $mAdminPrincipal] : [];
		$this->addPlugin($aclPlugin);

		/* DAV Sync Plugin */
		$this->addPlugin(
			new \Sabre\DAV\Sync\Plugin()
		);

		/* HTML Frontend Plugin */
		if (\Aurora\System\Api::GetModuleManager()->getModuleConfigValue('Dav', 'UseBrowserPlugin', false))
		{
			$this->addPlugin(
				new \Sabre\DAV\Browser\Plugin()
			);
		}

		/* Property Storage Plugin */
		$this->addPlugin(
			new \Sabre\DAV\PropertyStorage\Plugin(
					new \Afterlogic\DAV\PropertyStorage\Backend\PDO()
			)
		);

		$this->addPlugin(
			new \Sabre\DAV\Sharing\Plugin()
		);

		/* Locks Plugin */
//                $this->addPlugin(new \Sabre\DAV\Locks\Plugin());

		$oSettings = \Aurora\Api::GetSettings();
		if ($oSettings->GetConf('EnableLogging', false))
		{
			/* Logs Plugin */
			$this->addPlugin(new Logs\Plugin());
		}
	}

	protected function initAddressbooks()
	{
		if ($this->isModuleEnabled('Contacts') && $this->isModuleEnabled('MobileSync'))
		{
			$this->rootNode->addChild(
					new CardDAV\AddressBookRoot(
							Backend::Carddav()
					)
			);

			$carddavPlugin = new CardDAV\Plugin();
			if ($this->isModuleEnabled('TeamContacts'))
			{
				$this->rootNode->addChild(new CardDAV\GAB\AddressBook(
					'gab',
					Constants::GLOBAL_CONTACTS
				));
				$carddavPlugin->directories = ['gab'];
			}
			$this->addPlugin(
				$carddavPlugin
			);

			$this->addPlugin(
				new Contacts\Plugin()
			);


			/* VCF Export Plugin */
			$this->addPlugin(
				new \Sabre\CardDAV\VCFExportPlugin()
			);
		}
	}

	protected function initCalendars()
	{
		if ($this->isModuleEnabled('Calendar') && $this->isModuleEnabled('MobileSync'))
		{
			/* CalDAV Plugin */
			$this->addPlugin(
				new CalDAV\Plugin()
			);

			/* ICS Export Plugin */
			$this->addPlugin(
				new \Sabre\CalDAV\ICSExportPlugin()
			);

			$this->rootNode->addChild(
				new CalDAV\CalendarHome(
					Backend::Caldav()
				)
			);

			// $this->rootNode->addChild(
			// 	new CalDAV\CalendarRoot(
			// 		Backend::Caldav()
			// 	)
			// );

			/* Reminders Plugin */
			$this->addPlugin(
				new Reminders\Plugin(Backend::Reminders())
			);

			if ($this->isModuleEnabled('CorporateCalendar'))
			{
				/* Sharing Plugin */
				$this->addPlugin(
					new \Sabre\DAV\Sharing\Plugin()
				);

				/* Calendar Sharing Plugin */
				$this->addPlugin(
					new \Sabre\CalDAV\SharingPlugin()
				);
			}

			$this->addPlugin(
				new CalDAV\Schedule\Plugin()
			);

			$this->addPlugin(
				new CalDAV\Schedule\IMipPlugin()
			);
		}
	}

	protected function initFiles()
	{
		$sHeader = $this->httpRequest->getHeader('X-Client');
		if ($this->isModuleEnabled('Files') && ($this->isModuleEnabled('MobileSync') || $sHeader === 'WebClient'))
		{
			$this->addPlugin(
				new FS\Plugin()
			);

			// Automatically guess (some) contenttypes, based on extesion
			$this->addPlugin(
				new \Sabre\DAV\Browser\GuessContentType()
			);

			$oRoot = new \Afterlogic\DAV\FS\Root();

			 if ($oRoot->getChildrenCount() > 0)
			 {
				$this->rootNode->addChild($oRoot);
			 }
		}
	}

	protected function initPrincipals()
	{
		$oPrincipalColl = new \Sabre\DAVACL\PrincipalCollection(
			Backend::Principal()
		);
		$oPrincipalColl->disableListing = false;
		$this->rootNode->addChild($oPrincipalColl);
	}

	public function __construct()
	{
		$this->rootNode = new SimpleCollection('root');

		$this->on('propFind', [$this, 'onPropFind']);

		if (\Aurora\System\Api::GetPDO() && $this->isModuleEnabled('Dav'))
		{
			$this->initServer();

			$this->initAddressbooks();

			$this->initCalendars();

			$this->initFiles();

			$this->initPrincipals();
		}
    }

	public function exec()
	{
		$sRequestUri = empty($_SERVER['REQUEST_URI']) ? '' : \trim($_SERVER['REQUEST_URI']);

		if ($this->isModuleEnabled('Dav') && !strpos(urldecode($sRequestUri), '../'))
		{
			parent::exec();
		}
		else
		{
			echo 'Access denied';
		}
	}

	public static function setUser($sUserPublicId)
	{
		self::$sUserPublicId = $sUserPublicId;
	}

	public static function getUserObject()
	{
		if (null === self::$oUser)
		{
			self::$oUser = \Aurora\System\Api::getAuthenticatedUser();
		}
		return self::$oUser;
	}

	public static function getUser()
	{
		if (null === self::$sUserPublicId)
		{
			self::$sUserPublicId = \Aurora\System\Api::getAuthenticatedUserPublicId();
		}
		return self::$sUserPublicId;
	}

	/**
	 * @param string $sUserPublicId
	 *
	 * @return array
	 */
	public static function getPrincipalInfo($sUserPublicId)
	{
		$mPrincipal = [];

		if (isset($sUserPublicId))
		{
			$aPrincipalProperties = \Afterlogic\DAV\Backend::Principal()->getPrincipalByPath(
				\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $sUserPublicId
			);

			if (isset($aPrincipalProperties['uri']))
			{
				$mPrincipal['uri'] = $aPrincipalProperties['uri'];
				$mPrincipal['id'] = $aPrincipalProperties['id'];
			}
			else
			{
				$mPrincipal['uri'] = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $sUserPublicId;
				$mPrincipal['id'] = -1;
			}
		}

		return $mPrincipal;
	}

	/**
	 *
	 * @return array
	 */
	public static function getCurrentPrincipalInfo()
	{
		$principalInfo = [];
		$sUserPublicId = \Afterlogic\DAV\Server::getUser();
		if (!empty($sUserPublicId))
		{
			$principalInfo = self::getPrincipalInfo($sUserPublicId);
		}

		return $principalInfo;
	}

	public static function getTenantObject()
	{
		if (null === self::$oTenant)
		{
			$iIdTenant = self::getTenantId();
			if ($iIdTenant)
			{
				self::$oTenant = \Aurora\Modules\Core\Models\Tenant::select('Name')->find($iIdTenant);
			}
		}
		return self::$oTenant;
	}

	public static function getTenantId()
	{
		$iIdTenant = false;
		$oResult = \Aurora\Modules\Core\Models\User::select('IdTenant')->firstWhere('PublicId', self::getUser());
		if (isset($oResult->IdTenant))
		{
			$iIdTenant = (int) $oResult->IdTenant;
		}

		return $iIdTenant;
	}

	public static function getTenantName()
	{
		$sTanantName = null;

		$iIdTenant = self::getTenantId();
		if ($iIdTenant)
		{
			$oResult = \Aurora\Modules\Core\Models\Tenant::select('Name')->find($iIdTenant);
			if (isset($oResult) && isset($oResult->Name))
			{
				$sTanantName = $oResult->Name;
			}
		}

		return $sTanantName;
	}


	/**
	 * @param \Sabre\DAV\PropFind $propfind
	 * @param \Sabre\DAV\INode $node
	 * @return void
	 */
	public function onPropFind($propfind, \Sabre\DAV\INode $node)
	{
		$sUserPublicId = self::getUser();
		if (isset($sUserPublicId))
		{
			if ($this->isModuleEnabled('TeamContacts'))
			{
				if ($this->rootNode->childExists('gab'))
				{
					$oTenant = self::getTenantObject();
					$oUser = self::getUserObject();

					$bIsModuleDisabledForTenant = isset($oTenant) ? $oTenant->isModuleDisabled('TeamContacts') : false;
					$bIsModuleDisabledForUser = isset($oUser) ? $oUser->isModuleDisabled('TeamContacts') : false;

					if ($bIsModuleDisabledForTenant || $bIsModuleDisabledForUser)
					{
						$this->rootNode->deleteChild('gab');

						$carddavPlugin = $this->getPlugin('carddav');
						if ($carddavPlugin)
						{
							$carddavPlugin->directories = [];
						}

						$aclPlugin = $this->getPlugin('acl');
						if ($aclPlugin)
						{
							$aclPlugin->hideNodesFromListings = true;
						}
					}
				}
			}
		}
	}

	public static function getNodeForPath($path)
	{
		$oNode = false;
		$path = str_replace('//', '/', $path);
		self::getInstance()->setUser(self::getUser());
		try {
			$oNode = self::getInstance()->tree->getNodeForPath($path);
		}
		catch (\Exception $oEx) {}

		return $oNode;
	}

	public static function checkPrivileges($path, $priv)
	{
		$oServer = \Afterlogic\DAV\Server::getInstance();
		$oAclPlugin = $oServer->getPlugin('acl');
		$oAclPlugin->checkPrivileges($path, $priv);
	}
}
