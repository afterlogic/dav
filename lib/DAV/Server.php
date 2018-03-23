<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV;

class Server extends \Sabre\DAV\Server
{
	/**
	 * @var string
	 */
	public $sUserPublicId = null;
	
	/**
	 * @return \Afterlogic\DAV\Server
	 */
	static public function getInstance($baseUri = '/') 
	{ 
		static $oInstance = null;
		if(is_null($oInstance)) 
		{ 
			$oInstance = new self($baseUri); 
		} 
		return $oInstance; 
	}	

	public function __construct($baseUri = '/')
	{
		$this->debugExceptions = true;
		self::$exposeVersion = false;

		$this->setBaseUri($baseUri);
		date_default_timezone_set('GMT');

		if (\Aurora\System\Api::GetPDO()) 
		{
			/* Authentication Plugin */
			$this->addPlugin(new \Afterlogic\DAV\Auth\Plugin(Backend::Auth(), 'SabreDAV'));

			/* DAV ACL Plugin */
			$aclPlugin = new \Sabre\DAVACL\Plugin();
			$aclPlugin->hideNodesFromListings = true;
			$aclPlugin->defaultUsernamePath = Constants::PRINCIPALS_PREFIX;
			
			$oDavModule = /* @var $oDavModule \Aurora\Modules\Dav\Module */ \Aurora\System\Api::GetModule('Dav'); 
			$mAdminPrincipal = $oDavModule->getConfig('AdminPrincipal', false);
			$aclPlugin->adminPrincipals = ($mAdminPrincipal !== false) ?
					array(Constants::PRINCIPALS_PREFIX . '/' . $mAdminPrincipal) : array();
			$this->addPlugin($aclPlugin);

			$bIsOwncloud = false;
			/* Directory tree */
			$aTree = array(
				($bIsOwncloud) ? 
					new CardDAV\AddressBookRoot(
						Backend::Principal(), 
						Backend::getBackend('carddav-owncloud')
					) : 
					new CardDAV\AddressBookRoot(
						Backend::Principal(), 
						Backend::Carddav()
					),
				new CalDAV\CalendarRoot(
					Backend::Principal(), 
					Backend::Caldav()),
				new CardDAV\GAB\AddressBooks(
					'gab', 
					Constants::GLOBAL_CONTACTS
				), /* Global Address Book */
			);

			/* Files folder */
			if (/*$this->oApiCapaManager->isFilesSupported()*/ true) // todo
			{
				array_push($aTree, new \Afterlogic\DAV\FS\FilesRoot());
				
				$this->addPlugin(new FS\Plugin());

				// Automatically guess (some) contenttypes, based on extesion
				$this->addPlugin(new \Sabre\DAV\Browser\GuessContentType());				
			}
			
			$oPrincipalColl = new \Sabre\DAVACL\PrincipalCollection(Backend::Principal());
			$oPrincipalColl->disableListing = true;

			array_push($aTree, $oPrincipalColl);

			/* Initializing server */
			parent::__construct($aTree);
			$this->httpResponse->setHeader("X-Server", Constants::DAV_SERVER_NAME);
			
			/* Reminders Plugin */
			$this->addPlugin(new Reminders\Plugin(Backend::Reminders()));
			
			$this->addPlugin(new \Sabre\CalDAV\Schedule\Plugin());
			$this->addPlugin(new \Sabre\CalDAV\Schedule\IMipPlugin('test@local.host'));
			
			/* Contacts Plugin */
			$this->addPlugin(new Contacts\Plugin());

//			if ($this->oApiCapaManager->isMobileSyncSupported()) {
				
				/* CalDAV Plugin */
				$this->addPlugin(new \Sabre\CalDAV\Plugin());

				/* CardDAV Plugin */
				$this->addPlugin(new \Sabre\CardDAV\Plugin());
				
				/* ICS Export Plugin */
				$this->addPlugin(new \Sabre\CalDAV\ICSExportPlugin());

				/* VCF Export Plugin */
				$this->addPlugin(new \Sabre\CardDAV\VCFExportPlugin());
//			}

			/* Calendar Sharing Plugin */
			$this->addPlugin(new \Sabre\CalDAV\SharingPlugin());
			
			/* DAV Sync Plugin */
			$this->addPlugin(new \Sabre\DAV\Sync\Plugin());			

			/* HTML Frontend Plugin */
			if ($oDavModule->getConfig('UseBrowserPlugin', false) !== false) 
			{
				$this->addPlugin(new \Sabre\DAV\Browser\Plugin());
			}

			/* Property Storage Plugin */
			$this->addPlugin(
				new \Sabre\DAV\PropertyStorage\Plugin(
					new \Afterlogic\DAV\PropertyStorage\Backend\PDO()
				)
			);			

			/* Locks Plugin */
//			$this->addPlugin(new \Sabre\DAV\Locks\Plugin());

			/* Logs Plugin */
//			$this->addPlugin(new Logs\Plugin());

			$this->on('beforeGetProperties', array($this, 'beforeGetProperties'), 90);
		}
    }
	
	public static function getUser()
	{
		static $sUserPublicId = null;
		if (null === $sUserPublicId) 
		{
			$oUser = \Aurora\System\Api::getAuthenticatedUser();
			if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
			{
				$sUserPublicId = $oUser->PublicId;
			}
		}
		return $sUserPublicId;
	}	

	/**
	 * @param string $path
	 * @param \Sabre\DAV\INode $node
	 * @param array $requestedProperties
	 * @param array $returnedProperties
	 * @return void
	 */
	public function beforeGetProperties($path, \Sabre\DAV\INode $node, &$requestedProperties, &$returnedProperties)
	{
		$iUserId = self::getUser();
		if (isset($iUserId)/* && $node->getName() === 'root'*/)
		{
			$carddavPlugin = $this->getPlugin('carddav');
			if (isset($carddavPlugin)/* && $this->oApiCapaManager->isGlobalContactsSupported($iUserId, false)*/) // todo
			{
				$carddavPlugin->directories = array('gab');
			}
		}
	}
}