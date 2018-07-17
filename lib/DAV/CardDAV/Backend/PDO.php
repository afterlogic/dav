<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CardDAV\Backend;

use Afterlogic\DAV\Constants;

class PDO extends \Sabre\CardDAV\Backend\PDO {
	
	protected function getTenantPrincipal($sUserPublicId)
	{
		$sTenantPrincipal = 'default_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
		if ($oUser)
		{
			$sTenantPrincipal = $oUser->IdTenant . '_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
		}
		
		return 'principals/' . $sTenantPrincipal;
	}


	/**
     * Sets up the object
     */
    public function __construct() {

		parent::__construct(\Aurora\System\Api::GetPDO());
		$sDbPrefix = \Aurora\System\Api::GetSettings()->GetConf('DBPrefix');
		$this->cardsTableName = $sDbPrefix.Constants::T_CARDS;
		$this->addressBooksTableName = $sDbPrefix.Constants::T_ADDRESSBOOKS;
		$this->addressBookChangesTableName = $sDbPrefix.Constants::T_ADDRESSBOOKCHANGES;
    }
	
    /**
     * Returns the addressbook for a specific user.
     *
     * @param string $principalUri
     * @param string $addressbookUri
     * @return array
     */
    public function getAddressBookForUser($principalUri, $addressbookUri) {

		$mAddressBook = false;
		
        $stmt = $this->pdo->prepare('SELECT id, uri, displayname, principaluri, description FROM '.$this->addressBooksTableName.' WHERE principaluri = ? AND uri = ?');
        $stmt->execute(array($principalUri, $addressbookUri));

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
		
		if ($row)
		{
			$mAddressBook = [
				'id'  => $row['id'],
				'uri' => $row['uri'],
				'principaluri' => $row['principaluri'],
				'{DAV:}displayname' => $row['displayname'],
				'{' . \Sabre\CardDAV\Plugin::NS_CARDDAV . '}addressbook-description' => $row['description'],
	//			'{http://calendarserver.org/ns/}getctag' => $row['ctag'],
				'{' . \Sabre\CardDAV\Plugin::NS_CARDDAV . '}supported-address-data' => new \Sabre\CardDAV\Xml\Property\SupportedAddressData()
			];
		}
		
		return $mAddressBook;
    }	
	
    /**
     * Returns all cards for a specific addressbook id.
     *
     * @return array
     */
    public function getCardsSharedToAll($addressbookId) {

        $stmt = $this->pdo->prepare('SELECT id, carddata, uri, lastmodified FROM ' . $this->cardsTableName . ' WHERE addressbookid = ?');
        $stmt->execute(array($addressbookId));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);


    }
	
	/**
     * Returns all cards for a specific addressbook id.
     *
     * @param mixed $addressbookId
     * @return array
     */
    public function getCardsByOffset($addressbookId, $iOffset, $iRequestLimit) {

        $stmt = $this->pdo->prepare(
				'SELECT id, carddata, uri, lastmodified 
					FROM ' . $this->cardsTableName . ' 
						WHERE addressbookid = ? LIMIT ?, ?');
        $stmt->execute(array($addressbookId, $iOffset, $iRequestLimit));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);

    }	
	
    public function getSharedAddressBook($sPrincipalUri)
	{
		$sTenantPrincipal = $this->getTenantPrincipal(basename($sPrincipalUri));
		
		$aAddressBook = $this->getAddressBookForUser($sTenantPrincipal, \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME);
		
		if (!is_array($aAddressBook))
		{
			$sProperties = [
				'{DAV:}displayname' => \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_DISPLAY_NAME
			];

			$this->createAddressBook($sTenantPrincipal, \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME, $sProperties);
			$aAddressBook = $this->getAddressBookForUser($sTenantPrincipal, \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME);
		}
		
		return $aAddressBook;
	}
	
    /**
     * Returns a list of cards.
     *
     * This method should work identical to getCard, but instead return all the
     * cards in the list as an array.
     *
     * If the backend supports this, it may allow for some speed-ups.
     *
     * @param array $uris
     * @return array
     */
    function getMultipleSharedWithAllCards(array $uris) {

        $query = 'SELECT id, uri, lastmodified, etag, size, carddata FROM ' . $this->cardsTableName . ' WHERE uri IN (';
        // Inserting a whole bunch of question marks
        $query .= implode(',', array_fill(0, count($uris), '?'));
        $query .= ')';

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($uris);
        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $row['etag'] = '"' . $row['etag'] . '"';
            $row['lastmodified'] = (int)$row['lastmodified'];
            $result[] = $row;
        }
        return $result;

    }	
	
}

