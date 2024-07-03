<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV\Backend;

use Afterlogic\DAV\Constants;
use Aurora\Modules\Contacts\Models\ContactCard;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Reader;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class PDO extends \Sabre\CardDAV\Backend\PDO
{
    /**
     * PDO connection.
     *
     * @var \PDO
     */
    protected $pdo;

    protected $contactsCardsTableName;

    protected $sharedAddressBooksTableName;

    private string $cardsPropertiesTableName = '';

    protected function getTenantPrincipal($sUserPublicId)
    {
        $sTenantPrincipal = 'default_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
        $oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
        if ($oUser) {
            $sTenantPrincipal = $oUser->IdTenant . '_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
        }

        return \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $sTenantPrincipal;
    }


    /**
     * Sets up the object
     */
    public function __construct()
    {
        parent::__construct(\Aurora\System\Api::GetPDO());
        $sDbPrefix = \Aurora\System\Api::GetSettings()->DBPrefix;
        $this->cardsTableName = $sDbPrefix.Constants::T_CARDS;
        $this->addressBooksTableName = $sDbPrefix.Constants::T_ADDRESSBOOKS;
        $this->addressBookChangesTableName = $sDbPrefix.Constants::T_ADDRESSBOOKCHANGES;
        $this->contactsCardsTableName = $sDbPrefix.'contacts_cards';
        $this->sharedAddressBooksTableName = $sDbPrefix.'adav_shared_addressbooks';
    }

    /**
     * Returns the addressbook for a specific user.
     *
     * @param string $principalUri
     * @param string $addressbookUri
     * @return array
     */
    public function getAddressBookForUser($principalUri, $addressbookUri)
    {
        $mAddressBook = false;

        $stmt = $this->pdo->prepare('SELECT id, uri, displayname, principaluri, description, synctoken FROM '.$this->addressBooksTableName.' WHERE principaluri = ? AND uri = ?');
        $stmt->execute(array($principalUri, $addressbookUri));

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
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
    public function getCardsSharedToAll($addressbookId)
    {
        $stmt = $this->pdo->prepare('SELECT id, carddata, uri, lastmodified FROM ' . $this->cardsTableName . ' WHERE addressbookid = ?');
        $stmt->execute(array($addressbookId));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSharedWithAllAddressBook($sPrincipalUri)
    {
        $sTenantPrincipal = $this->getTenantPrincipal(basename($sPrincipalUri));

        $aAddressBook = $this->getAddressBookForUser($sTenantPrincipal, \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME);

        if (!is_array($aAddressBook)) {
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
    public function getMultipleSharedWithAllCards(array $uris)
    {
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

    public function getSharedAddressBooks($principalUri)
    {
        $sDbPrefix = \Aurora\System\Api::GetSettings()->DBPrefix;
        $stmt = $this->pdo->prepare('SELECT ab.id, sab.addressbookuri as uri, ab.displayname, ab.principaluri, ab.description, ab.synctoken, sab.access, sab.group_id
        FROM ' . $sDbPrefix . 'adav_addressbooks as ab, ' . $sDbPrefix . 'adav_shared_addressbooks as sab
        WHERE ab.id = sab.addressbook_id AND sab.principaluri = ?');
        $stmt->execute([$principalUri]);

        $addressBooks = [];

        foreach ($stmt->fetchAll() as $row) {
            $addressBooks[] = [
                'id' => $row['id'],
                'uri' => $row['uri'],
                'principaluri' => $row['principaluri'],
                '{DAV:}displayname' => $row['displayname'] . ' (' . basename($row['principaluri']) . ')',
                '{'.\Sabre\CardDAV\Plugin::NS_CARDDAV.'}addressbook-description' => $row['description'],
                '{http://calendarserver.org/ns/}getctag' => $row['synctoken'],
                '{http://sabredav.org/ns}sync-token' => $row['synctoken'] ? $row['synctoken'] : '0',
                'access' => $row['access'],
                'group_id' => $row['group_id']
            ];
        }

        return $addressBooks;
    }

    public function createCard($addressBookId, $cardUri, $cardData)
    {
        $result = parent::createCard($addressBookId, $cardUri, $cardData);
        $this->updateProperties($addressBookId, $cardUri, $cardData);

        return $result;
    }

    public function updateCard($addressBookId, $cardUri, $cardData)
    {
        $result = parent::updateCard($addressBookId, $cardUri, $cardData);
        $this->updateProperties($addressBookId, $cardUri, $cardData);

        return $result;
    }

    public function deleteCard($addressBookId, $cardUri)
    {
        $cardId = $this->getCardId($addressBookId, $cardUri);
        ContactCard::where('AddressBookId', $addressBookId)->where('CardId', $cardId)->delete();
        $this->purgeProperties($addressBookId, $cardUri);
        return parent::deleteCard($addressBookId, $cardUri);
    }

    /**
     * Get ID from a given contact
     */
    protected function getCardId(int $addressBookId, string $uri): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM ' . $this->cardsTableName . ' WHERE uri = ? AND addressbookid = ?');
        $stmt->execute([$uri, $addressBookId]);
        $cardIds = $stmt->fetch();

        if (!isset($cardIds['id'])) {
            throw new \InvalidArgumentException('Card does not exists: ' . $uri);
        }

        return (int) $cardIds['id'];
    }

    /**
     * update properties table
     *
     * @param int $addressBookId
     * @param string $cardUri
     * @param string $vCardSerialized
     */
    public function updateProperties($addressBookId, $cardUri, $vCardData)
    {
        $vCard = $this->readCard($vCardData);
        $cardId = $this->getCardId($addressBookId, $cardUri);
        $contactCard = ContactCard::firstOrNew(['CardId' => $cardId]);

        $contactCard->AddressBookId = $addressBookId;
        foreach ($vCard->children() as $property) {
            switch ($property->name) {
                case 'FN':
                    $contactCard->FullName = $property->getValue();
                    break;
                case 'N':
                    $nameParts = $property->getParts();
                    $contactCard->FirstName = $nameParts[0];
                    $contactCard->LastName = $nameParts[1];
                    break;
                case 'EMAIL':
                    $type = $property['TYPE'];
                    if ($type) {
                        if ($type->has('WORK') || $type->has('INTERNET')) {
                            $contactCard->BusinessEmail = (string) $property;
                            if ($type->has('PREF')) {
                                $contactCard->PrimaryEmail = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Business;
                                $contactCard->ViewEmail = $contactCard->BusinessEmail;
                            }
                        } elseif ($type->has('HOME')) {
                            $contactCard->BusinessEmail = (string) $property;
                            if ($type->has('PREF')) {
                                $contactCard->PrimaryEmail = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Personal;
                                $contactCard->ViewEmail = $contactCard->BusinessEmail;
                            }
                        } elseif ($type->has('OTHER')) {
                            $contactCard->OtherEmail = (string) $property;
                            if ($type->has('PREF')) {
                                $contactCard->PrimaryEmail = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other;
                                $contactCard->ViewEmail = $contactCard->OtherEmail;
                            }
                        } elseif ($property->group && isset($vCard->{$property->group.'.X-ABLABEL'}) &&
                            strtolower((string) $vCard->{$property->group.'.X-ABLABEL'}) === '_$!<other>!$_') {
                            $contactCard->OtherEmail = (string) $property;
                            if ($type->has('PREF')) {
                                $contactCard->PrimaryEmail = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other;
                                $contactCard->ViewEmail = $contactCard->OtherEmail;
                            }
                        }
                    } else {
                        $contactCard->OtherEmail = (string) $property;
                        $contactCard->PrimaryEmail = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other;
                        $contactCard->ViewEmail = $contactCard->OtherEmail;
                    }
                    if (empty($contactCard->PrimaryEmail)) {
                        if (!empty($contactCard->BusinessEmail)) {
                            $contactCard->PrimaryEmail = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Business;
                            $contactCard->ViewEmail = $contactCard->BusinessEmail;
                        } elseif (!empty($contactCard->PersonalEmail)) {
                            $contactCard->PrimaryEmail = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Personal;
                            $contactCard->ViewEmail = $contactCard->PersonalEmail;
                        } elseif (!empty($contactCard->OtherEmail)) {
                            $contactCard->PrimaryEmail = \Aurora\Modules\Contacts\Enums\PrimaryEmail::Other;
                            $contactCard->ViewEmail = $contactCard->OtherEmail;
                        }
                    }
                    break;
                case 'KIND':
                case 'X-ADDRESSBOOKSERVER-KIND':
                    if (strtoupper($property->getValue()) === 'GROUP') {
                        $contactCard->IsGroup = true;
                    }
                    break;
                case 'X-FREQUENCY':
                    $contactCard->Frequency = (int) $property->getValue();
            }
        }
        $contactCard->save();
    }

    /**
     * read vCard data into a vCard object
     *
     * @param string $cardData
     * @return VCard
     */
    protected function readCard($cardData)
    {
        return Reader::read($cardData);
    }

    /**
     * delete all properties from a given card
     *
     * @param int $addressBookId
     * @param int $cardId
     */
    protected function purgeProperties($addressBookId, $cardUri)
    {
        $cardId = $this->getCardId($addressBookId, $cardUri);
        $stmt = $this->pdo->prepare('DELETE FROM ' . $this->contactsCardsTableName . ' WHERE addressbookid = ? AND cardid = ?');
        $stmt->execute([$addressBookId, $cardId]);
    }

    /**
     * Deletes an entire addressbook and all its contents.
     *
     * @param int $addressBookId
     */
    public function deleteAddressBook($addressBookId)
    {
        parent::deleteAddressBook($addressBookId);

        $stmt = $this->pdo->prepare('DELETE FROM '.$this->contactsCardsTableName.' WHERE AddressBookId = ?');
        $stmt->execute([$addressBookId]);

        $sharedAddressBooksTableExists = false;
        try {
            $this->pdo->query('SELECT 1 FROM ' . $this->sharedAddressBooksTableName);
            $sharedAddressBooksTableExists = true;
        } catch (\PDOException $e){
            $sharedAddressBooksTableExists = false;
        }
        
        if ($sharedAddressBooksTableExists) {
            $stmt = $this->pdo->prepare('DELETE FROM '.$this->sharedAddressBooksTableName.' WHERE addressbook_id = ?');
            $stmt->execute([$addressBookId]);
        }
    }

    /**
     * Returns the addressbook for a specific user.
     *
     * @param string $principalUri
     * @param int $addressbookId
     * @return array|bool
     */
    public function getAddressBookByIdForUser($principalUri, $addressbookId)
    {
        $mAddressBook = false;

        $stmt = $this->pdo->prepare('SELECT id, uri, displayname, principaluri, description, synctoken FROM '.$this->addressBooksTableName.' WHERE principaluri = ? AND id = ?');
        $stmt->execute(array($principalUri, $addressbookId));

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
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
     * Returns the addressbook by a specific id.
     *
     * @param int $addressbookId
     * @return array|bool
     */
    public function getAddressBookById($addressbookId)
    {
        $mAddressBook = false;

        $stmt = $this->pdo->prepare('SELECT id, uri, displayname, principaluri, description, synctoken FROM '.$this->addressBooksTableName.' WHERE id = ?');
        $stmt->execute(array($addressbookId));

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
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

    public function updateCardAddressBook($addressBookId, $newAddressBookId, $cardUri)
    {
        $result = false;
        $card = $this->getCard($addressBookId, $cardUri);
        if ($card) {
            $stmt = $this->pdo->prepare('UPDATE '.$this->cardsTableName.' SET lastmodified = ?, addressbookid = ? WHERE uri = ? AND addressbookid = ?');
            $stmt->execute([
                time(),
                $newAddressBookId,
                $cardUri,
                $addressBookId,
            ]);
    
            ContactCard::where('CardId', $card['id'])->update(['AddressBookId' => $newAddressBookId]);
            
            $this->addChange($addressBookId, $cardUri, 3);
            $this->addChange($newAddressBookId, $cardUri, 1);

            $result = true;
        }

        return $result;
    }
}