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
		$oModule = /* @var $oModule \Aurora\Modules\Dav\Module */ \Aurora\System\Api::GetModule($sModule); 
		return ($oModule && !$oModule->getConfig('Disabled', false));
	}
	
	protected function initServer()
	{
		/* Initializing server */
		parent::__construct();

		$this->debugExceptions = true;
		self::$exposeVersion = false;

		$this->httpResponse->setHeader("X-Server", Constants::DAV_SERVER_NAME);
		$this->httpResponse->setHeader('Cache-Control', 'no-cache');

		/* Authentication Plugin */
		$this->addPlugin(
			new \Afterlogic\DAV\Auth\Plugin(Backend::Auth())
		);

		/* DAV ACL Plugin */
		$aclPlugin = new \Sabre\DAVACL\Plugin();
		$aclPlugin->hideNodesFromListings = true;
		$aclPlugin->allowUnauthenticatedAccess = false;
		$aclPlugin->defaultUsernamePath = \rtrim(Constants::PRINCIPALS_PREFIX, '/');

		$oDavModule = /* @var $oModule \Aurora\Modules\Dav\Module */ \Aurora\System\Api::GetModule('Dav'); 

		$mAdminPrincipal = $oDavModule->getConfig('AdminPrincipal', false);
		$aclPlugin->adminPrincipals = ($mAdminPrincipal !== false) ?
						[Constants::PRINCIPALS_PREFIX . $mAdminPrincipal] : [];
		$this->addPlugin($aclPlugin);

		/* DAV Sync Plugin */
		$this->addPlugin(
			new \Sabre\DAV\Sync\Plugin()
		);			

		/* HTML Frontend Plugin */
		if ($oDavModule->getConfig('UseBrowserPlugin', false)) 
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

		/* Logs Plugin */
//    $this->addPlugin(new Logs\Plugin());
		
	}
	
	protected function initAddressbooks()
	{
		if ($this->isModuleEnabled('Contacts') && $this->isModuleEnabled('MobileSync'))
		{
			$rootNode = $this->tree->getNodeForPath('');
			
			$bIsOwncloud = false;
			$rootNode->addChild(
				($bIsOwncloud) ? 
					new CardDAV\AddressBookRoot(
							Backend::getBackend('carddav-owncloud')
					) : 
					new CardDAV\AddressBookRoot(
							Backend::Carddav()
					)
			);

			$carddavPlugin = new CardDAV\Plugin();

			if ($this->isModuleEnabled('TeamContacts'))
			{
				/* Global Address Book */                                        
				$rootNode->addChild(new CardDAV\GAB\AddressBook(
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
			$rootNode = $this->tree->getNodeForPath('');

			/* CalDAV Plugin */
			$this->addPlugin(
				new CalDAV\Plugin()
			);

			/* ICS Export Plugin */
			$this->addPlugin(
				new \Sabre\CalDAV\ICSExportPlugin()
			);

			$rootNode->addChild(
				new CalDAV\CalendarHome(
					Backend::Caldav()
				)
			);

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
				new \Sabre\CalDAV\Schedule\Plugin()
			);

/*			
			$this->addPlugin(
				new \Sabre\CalDAV\Schedule\IMipPlugin('test@local.host')
			);
	*/
		}
	}	
	
	protected function initFiles()
	{
		$sHeader = $this->httpRequest->getHeader('X-Client');
		if ($this->isModuleEnabled('Files') && ($this->isModuleEnabled('MobileSync') || $sHeader === 'WebClient'))
		{
			$rootNode = $this->tree->getNodeForPath('');

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
				$rootNode->addChild($oRoot);
			 }
		}
	}
	
	protected function initPrincipals()
	{
		$rootNode = $this->tree->getNodeForPath('');
		
		$oPrincipalColl = new \Sabre\DAVACL\PrincipalCollection(
			Backend::Principal()
		);
		$oPrincipalColl->disableListing = false;
		$rootNode->addChild($oPrincipalColl);				
	}

	public function __construct()
	{
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
		if ($this->isModuleEnabled('Dav')) 
		{
			parent::exec();
		}
	}
	
	public static function setUser($sUserPublicId)
	{
		self::$sUserPublicId = $sUserPublicId;
	}	

	public static function getUser()
	{
		if (null === self::$sUserPublicId) 
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();

			if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
			{
				self::$sUserPublicId = $oUser->PublicId;
			}
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

	public static function getTenantName()
	{
		$sTanantName = null;
		$oUser = \Aurora\Modules\Core\Module::getInstance()->GetUserByPublicId(
			self::getUser()
		);
		if ($oUser)
		{
			$oTenant = \Aurora\Modules\Core\Module::getInstance()->GetTenantUnchecked(
				$oUser->IdTenant
			);
			if ($oTenant)
			{
				$sTanantName = $oTenant->Name;
			}
		}

		return $sTanantName;
	}
}
