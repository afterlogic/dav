<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV\Backend;

use Afterlogic\DAV\Constants;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class PDO extends \Sabre\CardDAV\Backend\PDO {

	protected function getTenantPrincipal($sUserPublicId)
	{
		$sTenantPrincipal = 'default_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
		if ($oUser)
		{
			$sTenantPrincipal = $oUser->IdTenant . '_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
		}

		return \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $sTenantPrincipal;
	}


	/**
     * Sets up the object
     */
    public function __construct() {

		parent::__construct(\Aurora\System\Api::GetPDO());
		$sDbPrefix = \Aurora\System\Api::GetSettings()->DBPrefix;
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

        $stmt = $this->pdo->prepare('SELECT id, uri, displayname, principaluri, description, synctoken FROM '.$this->addressBooksTableName.' WHERE principaluri = ? AND uri = ?');
        $stmt->execute(array($principalUri, $addressbookUri));

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

		if ($row)
		{
			$mAddressBook = [
                'id'                                                          => $row['id'],
                'uri'                                                         => $row['uri'],
                'principaluri'                                                => $row['principaluri'],
                '{DAV:}displayname'                                           => $row['displayname'],
                '{' . \Sabre\CardDAV\Plugin::NS_CARDDAV . '}addressbook-description' => $row['description'],
                '{http://calendarserver.org/ns/}getctag'                      => $row['synctoken'],
                '{http://sabredav.org/ns}sync-token'                          => $row['synctoken'] ? $row['synctoken'] : '0',
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
