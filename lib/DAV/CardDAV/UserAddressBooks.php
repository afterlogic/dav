<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CardDAV;

class UserAddressBooks extends \Sabre\CardDAV\AddressBookHome {

	/**
     * Returns a list of addressbooks
     *
     * @return array
     */
    public function getChildren() 
	{
        $objs = array();
		/* @var $oApiCapaManager \CApiCapabilityManager */
		$oApiCapaManager = \CApi::GetCoreManager('capability');
		
		$addressbooks = $this->carddavBackend->getAddressbooksForUser($this->principalUri);
		if (count($addressbooks) === 0)
		{
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
			$addressbooks = $this->carddavBackend->getAddressbooksForUser($this->principalUri);
		}
		foreach($addressbooks as $addressbook) 
		{
			$objs[] = new AddressBook($this->carddavBackend, $addressbook);
		}
		if ($oApiCapaManager->isCollaborationSupported())
		{
			$sharedAddressbook = $this->carddavBackend->getSharedAddressBook($this->principalUri);
			$objs[] = new SharedAddressBook($this->carddavBackend, $sharedAddressbook, $this->principalUri);
		}
        return $objs;

    }	
}