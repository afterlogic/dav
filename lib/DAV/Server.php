<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV;

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
	
	protected function initAddressbooks($rootNode)
	{
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
	
	protected function initCalendars($rootNode)
	{
		/* CalDAV Plugin */
		$this->addPlugin(
			new CalDAV\Plugin()
		);

		/* ICS Export Plugin */
		$this->addPlugin(
			new \Sabre\CalDAV\ICSExportPlugin()
		);

		$rootNode->addChild(
			new CalDAV\CalendarRoot(
				Backend::Caldav()
			)
		);

		/* Reminders Plugin */
		$this->addPlugin(
			new Reminders\Plugin(Backend::Reminders())
		);

		$oCorporateCalendar = \Aurora\System\Api::GetModule('CorporateCalendar');
		if ($oCorporateCalendar && !$oCorporateCalendar->getConfig('Disabled', false))
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

/*
		$this->addPlugin(
			new \Sabre\CalDAV\Schedule\Plugin()
		);
		$this->addPlugin(
			new \Sabre\CalDAV\Schedule\IMipPlugin('test@local.host')
		);
*/
	}	
	
	protected function initFiles($rootNode)
	{
		$this->addPlugin(
			new FS\Plugin()
		);

		// Automatically guess (some) contenttypes, based on extesion
		$this->addPlugin(
			new \Sabre\DAV\Browser\GuessContentType()
		);				

		$rootNode->addChild(
			new \Afterlogic\DAV\FS\FilesRoot()
		);
	}
	
	protected function isModuleEnabled($sModule)
	{
		$oModule = /* @var $oModule \Aurora\Modules\Dav\Module */ \Aurora\System\Api::GetModule($sModule); 
		return ($oModule && !$oModule->getConfig('Disabled', false));
	}

	public function __construct()
	{
		if (\Aurora\System\Api::GetPDO()) 
		{
			if ($this->isModuleEnabled('Dav'))
			{
				$oDavModule = /* @var $oModule \Aurora\Modules\Dav\Module */ \Aurora\System\Api::GetModule('Dav'); 

				/* Initializing server */
				parent::__construct();

				$this->debugExceptions = true;
				self::$exposeVersion = false;
				
				$this->httpResponse->setHeader("X-Server", Constants::DAV_SERVER_NAME);

				/* Authentication Plugin */
				$this->addPlugin(
					new \Afterlogic\DAV\Auth\Plugin(Backend::Auth())
				);

				/* DAV ACL Plugin */
				$aclPlugin = new \Sabre\DAVACL\Plugin();
				$aclPlugin->hideNodesFromListings = true;
				$aclPlugin->defaultUsernamePath = Constants::PRINCIPALS_PREFIX;
				
				$mAdminPrincipal = $oDavModule->getConfig('AdminPrincipal', false);
				$aclPlugin->adminPrincipals = ($mAdminPrincipal !== false) ?
								[Constants::PRINCIPALS_PREFIX . '/' . $mAdminPrincipal] : [];
				$this->addPlugin($aclPlugin);
				
				$rootNode = $this->tree->getNodeForPath('');
				
				$bIsMobileSyncEnabled = $this->isModuleEnabled('MobileSync');

				if ($this->isModuleEnabled('Contacts') && $bIsMobileSyncEnabled)
				{
					$this->initAddressbooks($rootNode);
				}
				
				if ($this->isModuleEnabled('Calendar') && $bIsMobileSyncEnabled)
				{
					$this->initCalendars($rootNode);
				}
				
				if ($this->isModuleEnabled('Files'))
				{
					$this->initFiles($rootNode);
				}

				$oPrincipalColl = new \Sabre\DAVACL\PrincipalCollection(
					Backend::Principal()
				);
				$oPrincipalColl->disableListing = false;
				$rootNode->addChild($oPrincipalColl);				
				
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

				/* Locks Plugin */
//                $this->addPlugin(new \Sabre\DAV\Locks\Plugin());

				/* Logs Plugin */
//                $this->addPlugin(new Logs\Plugin());
			}
		}
    }
	
	public function exec() {
		if ($this->isModuleEnabled('Dav')) {
			parent::exec();
		}
	}
	
	/*
	 * 
	 */
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
				\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sUserPublicId
			);
			
			if (isset($aPrincipalProperties['uri'])) 
			{
				$mPrincipal['uri'] = $aPrincipalProperties['uri'];
				$mPrincipal['id'] = $aPrincipalProperties['id'];
			} 
			else 
			{
				$mPrincipal['uri'] = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sUserPublicId;
				$mPrincipal['id'] = -1;
			}
		}
		
		return $mPrincipal;
	}
}