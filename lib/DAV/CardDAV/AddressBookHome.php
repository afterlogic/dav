<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CardDAV;

class AddressBookHome extends \Sabre\CardDAV\AddressBookHome {

	protected $isEmpty = false;
	
	public function setEmpty($isEmpty)
	{
		$this->isEmpty = $isEmpty;
	}

	/**
     * Returns a list of addressbooks
     *
     * @return array
     */
    public function getChildren() 
	{
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
		if (/*$oApiCapaManager->isCollaborationSupported()*/ true) { // TODO
			
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