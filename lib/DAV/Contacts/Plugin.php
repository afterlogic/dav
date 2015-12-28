<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Contacts;

class Plugin extends \Sabre\DAV\ServerPlugin
{

    /**
     * Reference to main server object
     *
     * @var \Sabre\DAV\Server
     */
    private $server;
	
	private $oApiContactsManager;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
		$oContactsModule = \CApi::GetModule('Contacts');
		if ($oContactsModule instanceof \AApiModule) {
			$this->oApiContactsManager = $oContactsModule->GetManager('main');
		}
	}

    public function initialize(\Sabre\DAV\Server $server)
    {
        $this->server = $server;
		$this->server->on('beforeUnbind', array($this, 'beforeUnbind'),30);
        $this->server->on('afterUnbind', array($this, 'afterUnbind'),30);
		$this->server->on('afterWriteContent', array($this, 'afterWriteContent'), 30);
		$this->server->on('afterCreateFile', array($this, 'afterCreateFile'), 30);
    }

    /**
     * Returns a plugin name.
     *
     * Using this name other plugins will be able to access other plugins
     * using \Sabre\DAV\Server::getPlugin
     *
     * @return string
     */
    public function getPluginName()
    {
        return 'contacts';
    }

    /**
     * @param string $path
     * @throws \Sabre\DAV\Exception\NotAuthenticated
     * @return bool
     */
    public function beforeUnbind($path)
    {
		return true;
	}    
	
	/**
     * @param string $path
     * @throws \Sabre\DAV\Exception\NotAuthenticated
     * @return bool
     */
    public function afterUnbind($path)
    {
		$oAccount = $this->server->getAccount();
		if (isset($oAccount)) {
			
			$oContact = $this->oApiContactsManager->getContactByStrId(
					$oAccount->IdUser, 
					basename($path)
			);

			if ($oContact) {
				$this->oApiContactsManager->deleteContacts(
						$oAccount->IdUser, 
						array($oContact->IdContact)
				);
			}
		}
		return true;
	}
	
	function afterCreateFile($path, \Sabre\DAV\ICollection $parent)
	{
		$sFileName = basename($path);
		$node = $parent->getChild($sFileName);
		if ($node instanceof \Sabre\CardDAV\ICard) {
			
			$oAccount = $this->server->getAccount();
			if (isset($oAccount)) {
				
				$oContact = new \CContact();
				$oContact->InitFromVCardStr($oAccount->IdUser, $node->get());
				$oContact->IdContactStr = $sFileName;
				$this->oApiContactsManager->createContact($oContact);
			}
		}
	}

	function afterWriteContent($path, \Sabre\DAV\IFile $node)
	{
		if ($node instanceof \Sabre\CardDAV\ICard) {
			
			$oAccount = $this->server->getAccount();
			if (isset($oAccount)) {
				
				$iUserId = $oAccount->IdUser;
				$iTenantId = ($node instanceof \Afterlogic\DAV\CardDAV\SharedCard) ? $oAccount->IdTenant : null;

				$sContactFileName = $node->getName();
				$oContactDb = $this->oApiContactsManager->getContactByStrId(
						$iUserId, 
						$sContactFileName, 
						$iTenantId
				);
				if (!isset($oContactDb)) {
					
					$oVCard = \Sabre\VObject\Reader::read(
							$node->get(), 
							\Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
					);

					if ($oVCard && $oVCard->UID) {
						
						$oContactDb = $this->oApiContactsManager->getContactByStrId(
								$iUserId, 
								(string)$oVCard->UID . '.vcf', 
								$iTenantId
						);
					}
				}

				$oContact = new \CContact();
				$oContact->InitFromVCardStr($iUserId, $node->get());
				$oContact->IdContactStr = $sContactFileName;
				$oContact->IdTenant = $iTenantId;

				if (isset($oContactDb)) {
					
					$oContact->IdContact = $oContactDb->IdContact;
					$oContact->IdDomain = $oContactDb->IdDomain;
					$oContact->SharedToAll = !!$oContactDb->SharedToAll;

					$this->oApiContactsManager->updateContact($oContact);
				} else {
					
					$this->oApiContactsManager->createContact($oContact);
				}
			}
		}
	}

}
