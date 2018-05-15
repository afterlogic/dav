<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CardDAV\Shared;

class AddressBook extends \Afterlogic\DAV\CardDAV\AddressBook {
    
	protected $principalUri;
	
	protected $oUser = null;

	protected $oApiContactsManager;
	
	public function getContactsManager()
	{
		if (!isset($this->oApiContactsManager))
		{
			$oContactsModule = \Aurora\System\Api::GetModule('Contacts');
			if ($oContactsModule instanceof \Aurora\System\Module\AbstractModule) 
			{
				
				$this->oApiContactsManager = $oContactsModule->oApiContactsManager;
			}
		}
		return $this->oApiContactsManager;
	}

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

	public function getUser() {
		
		if (null === $this->oUser) {
			
			$this->oUser = \Aurora\System\Api::GetModule('Core')->getUserByPublicId(
					basename($this->principalUri)
			);
		}
		return $this->oUser;
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
		
		$oUser = $this->getUser();

		$bResult = $this->getChildObj($oUser->EntityId, $name);
		
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

		$iUserId = $this->getUser();
		if ($iUserId) {
			/* @var $oApiContactsManager \CApiContactsMainManager */
			$oContacts = \Aurora\System\Api::GetModule('Contacts');
			
			$aContacts = $oContacts->GetContacts('shared', 0, 0);

			foreach ($aContacts['List'] as $aContact) {
				
				$child = $this->getChildObj($aContact['IdUser'], $aContact['UUID'] . '.vcf');
				if ($child) {
					
					$children[] = $child;
				}
			}

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
