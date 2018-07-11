<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CardDAV\Shared;

class AddressBook extends \Afterlogic\DAV\CardDAV\AddressBook {
    
	protected $principalUri;
	
	/**
     * Constructor
     *
     * @param Backend\BackendInterface $carddavBackend
     * @param array $addressBookInfo
     */
    public function __construct(\Sabre\CardDAV\Backend\BackendInterface $carddavBackend, array $addressBookInfo, $principalUri) {
        
		parent::__construct($carddavBackend, $addressBookInfo);
		$this->principalUri = $principalUri;
		
    }	
	
    function getChildACL() {

        return [
            [
                'privilege' => '{DAV:}all',
                'principal' => $this->principalUri,
                'protected' => true,
            ],
        ];

    }	
	
    function getOwner() {

        return $this->principalUri;

    }	

	/**
     * Returns a card
     *
     * @param int $iUserId
     * @param string $sContactId
     * @return \Sabre\CardDAV\\ICard
     */
    public function getChildObj($iUserId, $sContactId) {
		
		$oResult = null;

		$sUserPublicId = \Aurora\System\Api::getUserPublicIdById($iUserId);
		
		if ($sUserPublicId) {
			$aAddressBook = $this->carddavBackend->getAddressBookForUser(
					\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sUserPublicId, 
					\Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME
			);
			if ($aAddressBook) {
				
				$obj = $this->carddavBackend->getCard(
						$aAddressBook['id'], 
						$sContactId
				);
				if (is_array($obj)) {
					
					$oResult = new Card(
							$this->carddavBackend, 
							$aAddressBook, 
							$obj, 
							$this->principalUri
					);
				}
			}
		}
		
		return $oResult;
	}
	
	/**
     * Returns a card
     *
     * @param string $name
     * @return \Sabre\CardDAV\\ICard
     */
    public function getChild($name) {
		
		$bResult = false;

		/* @var $oApiContactsManager \CApiContactsMainManager */
		$oContacts = \Aurora\System\Api::GetModuleDecorator('Contacts');
		
		$oContact = $oContacts->GetContact(pathinfo($name, PATHINFO_FILENAME));
		if ($oContact)
		{
			$bResult = $this->getChildObj($oContact->IdUser, $name);
		}

		if (!isset($bResult)) {

			throw new \Sabre\DAV\Exception\NotFound('Card not found');
		}
		
        return $bResult;
    }

    /**
     * Returns the full list of cards
     *
     * @return array
     */
    public function getChildren() {

        $children = array();

		/* @var $oApiContactsManager \CApiContactsMainManager */
		$oContacts = \Aurora\System\Api::GetModuleDecorator('Contacts');

		$aContacts = $oContacts->GetContacts('shared', 0, 0);
		
		foreach ($aContacts['List'] as $aContact) {

			$child = $this->getChildObj($aContact['IdUser'], $aContact['UUID'] . '.vcf');
			if ($child) {

				$children[] = $child;
			}
		}

        return $children;
    }
	
    /**
     * This method receives a list of paths in it's first argument.
     * It must return an array with Node objects.
     *
     * If any children are not found, you do not have to return them.
     *
     * @param string[] $paths
     * @return array
     */
    function getMultipleChildren(array $paths) {

        $objs = $this->carddavBackend->getMultipleSharedWithAllCards($paths);
		
        $children = [];
        foreach ($objs as $obj) {
            $obj['acl'] = $this->getChildACL();
            $children[] = new Card($this->carddavBackend, $this->addressBookInfo, $obj, $this->principalUri);
        }
        return $children;

	}	
	
    public function createFile($name,$vcardData = null) {

        throw new \Sabre\DAV\Exception\Forbidden(
				'Permission denied to create file (filename ' . $name . ')'
		);
    }

    public function delete() {

        throw new \Sabre\DAV\Exception\Forbidden('Could not delete addressbook');

    }	
}
