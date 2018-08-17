<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CardDAV;

class AddressBookRoot extends \Sabre\CardDAV\AddressBookHome {

	protected $isEmpty = false;
	
	public function setEmpty($isEmpty)
	{
		$this->isEmpty = $isEmpty;
	}

	public function getName() {
		
		return 'addressbooks';
		
	}
	
	public function __construct(\Sabre\CardDAV\Backend\BackendInterface $caldavBackend, $principalInfo = null) {
		
		
		
		parent::__construct($caldavBackend, $principalInfo);
	}
	
	public function init() {
		
		if (!isset($this->principalUri))
		{
			$sUserPublicId = \Afterlogic\DAV\Server::getUser();
		
			if (!empty($sUserPublicId))
			{
				$principalInfo = \Afterlogic\DAV\Server::getPrincipalInfo($sUserPublicId);
				if (is_array($principalInfo))
				{
					$this->principalUri = $principalInfo['uri'];
				}
			}
		}
	}
	
	public function getACL() {
		
		$this->init();

		return parent::getACL();
	}
	
	public function getChild($name) {
		
		$this->init();
		return parent::getChild($name);
	}

	/**
     * Returns a list of addressbooks
     *
     * @return array
     */
    public function getChildren() 
	{
		$this->init();
        $objs = array();
		
		if ($this->isEmpty) {
			
			return $objs;
			
		}
		
		$aAddressbooks = $this->carddavBackend->getAddressbooksForUser($this->principalUri);
		if (count($aAddressbooks) === 0) {
			
			$this->carddavBackend->createAddressBook(
				$this->principalUri, 
				\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME, 
				[
					'{DAV:}displayname' => \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_DISPLAY_NAME
				]
			);
			$this->carddavBackend->createAddressBook(
				$this->principalUri, 
				\Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME, 
				[
					'{DAV:}displayname' => \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_DISPLAY_NAME
				]
			);
			$aAddressbooks = $this->carddavBackend->getAddressbooksForUser($this->principalUri);
		}
		foreach($aAddressbooks as $addressbook) {
			
			$objs[] = new AddressBook(
					$this->carddavBackend, 
					$addressbook
			);
		}
		$SharedContactsModule = \Aurora\System\Api::GetModule('SharedContacts');
		if ($SharedContactsModule && !$SharedContactsModule->getConfig('Disabled', true)) {
			
			$sharedAddressbook = $this->carddavBackend->getSharedAddressBook($this->principalUri);
			$objs[] = new Shared\AddressBook(
					$this->carddavBackend, 
					$sharedAddressbook, 
					$this->principalUri
			);
		}
        return $objs;

    }	
}