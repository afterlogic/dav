<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CardDAV\Shared;

class AddressBook extends \Afterlogic\DAV\CardDAV\AddressBook {
    
	protected $principalUri;
	
	/* @var int $iUserId */
	protected $iUserId = null;

	/* @var $oApiUsersManager \CApiUsersManager */
	protected $oApiUsersManager;

	protected $oApiContactsManager;
	
	public function getUsersManager()
	{
		if (!isset($this->oApiUsersManager)) {
			
			$this->oApiUsersManager = \Aurora\System\Api::GetSystemManager('users');
		}
		return $this->oApiUsersManager;
	}
	
	public function getContactsManager()
	{
		if (!isset($this->oApiContactsManager))
		{
			$oContactsModule = \Aurora\System\Api::GetModule('Contacts');
			if ($oContactsModule instanceof \AApiModule) {
				
				$this->oApiContactsManager = $oContactsModule->GetManager('main');
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
		
		if (null === $this->iUserId) {
			
			$this->iUserId = \Afterlogic\DAV\Utils::GetAccountByLogin(
					basename($this->principalUri)
			);
		}
		return $this->iUserId;
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

		/* @var $oApiUsersManager \CApiUsersManager */
		$oApiUsersManager = $this->getUsersManager();

		/* @var $oAccount \CAccount */
		$oAccount = $oApiUsersManager->getAccountById(
				$oApiUsersManager->getDefaultAccountId($iUserId)
		);
		
		if ($oAccount) {
			$aAddressBook = $this->carddavBackend->getAddressBookForUser(
					\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $oAccount->Email, 
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

		$bResult = null;
		/* @var $oApiContactsManager \CApiContactsMainManager */
		$oApiContactsManager = $this->getContactsManager();
		
		$iUserId = $this->getUser();

		/* @var $oContact \CContact */
		$oContact = $oApiContactsManager->getContactByStrId(
				$iUserId, 
				$name, 
				0 // TODO: IdTenant
		);
		if ($oContact) {
			
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
        return $children; // todo

		$iUserId = $this->getUser();
		if ($iUserId) {
			/* @var $oApiContactsManager \CApiContactsMainManager */
			$oApiContactsManager = $this->getContactsManager();

			$aContactListItems = $oApiContactsManager->getContactItems(
					$iUserId, 
					\EContactSortField::EMail, 
					\ESortOrder::ASC, 
					0, 
					999, 
					'', 
					'', 
					'', 
					0 // TODO: IdTenent
			);
			foreach ($aContactListItems as $oContactListItem) {
				
				$child = $this->getChildObj(
						$oContactListItem->IdUser, 
						$oContactListItem->IdStr
				);
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
