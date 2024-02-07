<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV;

use Afterlogic\DAV\Constants;
use Aurora\Modules\Contacts\Enums\Access;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class AddressBookRoot extends \Sabre\CardDAV\AddressBookHome
{
    protected $isEmpty = false;

    public function setEmpty($isEmpty)
    {
        $this->isEmpty = $isEmpty;
    }

    public function getName()
    {
        return 'addressbooks';
    }

    public function __construct(\Sabre\CardDAV\Backend\BackendInterface $caldavBackend, $principalInfo = null)
    {
        parent::__construct($caldavBackend, $principalInfo);
    }

    public function init()
    {
        if (!isset($this->principalUri)) {
            $sUserPublicId = \Afterlogic\DAV\Server::getUser();

            if (!empty($sUserPublicId)) {
                $principalInfo = \Afterlogic\DAV\Server::getPrincipalInfo($sUserPublicId);
                if (is_array($principalInfo)) {
                    $this->principalUri = $principalInfo['uri'];
                }
            }
        }
    }

    public function getACL()
    {
        $this->init();

        return parent::getACL();
    }

    public function getChild($name)
    {
        $this->init();
        if ($name === Constants::ADDRESSBOOK_DEFAULT_NAME || $name === Constants::ADDRESSBOOK_COLLECTED_NAME) {
            $abook = false;
            if ($this->carddavBackend instanceof \Afterlogic\DAV\CardDAV\Backend\PDO) {
                $abook = $this->carddavBackend->getAddressBookForUser($this->principalUri, $name);
            }
            if (!$abook && isset($this->principalUri)) {
                $this->carddavBackend->createAddressBook(
                    $this->principalUri,
                    $name,
                    [
                        '{DAV:}displayname' => $name === Constants::ADDRESSBOOK_DEFAULT_NAME ? Constants::ADDRESSBOOK_DEFAULT_DISPLAY_NAME : Constants::ADDRESSBOOK_COLLECTED_DISPLAY_NAME
                    ]
                );
            }
        }
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
        $objs = [];

        if ($this->isEmpty) {
            return $objs;
        }

        $aAddressbooks = $this->carddavBackend->getAddressbooksForUser($this->principalUri);
        if (count($aAddressbooks) === 0 && isset($this->principalUri)) {
            $this->carddavBackend->createAddressBook(
                $this->principalUri,
                Constants::ADDRESSBOOK_DEFAULT_NAME,
                [
                    '{DAV:}displayname' => Constants::ADDRESSBOOK_DEFAULT_DISPLAY_NAME
                ]
            );
            $this->carddavBackend->createAddressBook(
                $this->principalUri,
                Constants::ADDRESSBOOK_COLLECTED_NAME,
                [
                    '{DAV:}displayname' => Constants::ADDRESSBOOK_COLLECTED_DISPLAY_NAME
                ]
            );
            $aAddressbooks = $this->carddavBackend->getAddressbooksForUser($this->principalUri);
        }
        foreach ($aAddressbooks as $addressbook) {
            if ($addressbook['uri'] === 'Collected') {
                $objs[] = new Collected\AddressBook(
                    $this->carddavBackend,
                    $addressbook
                );
            } else {
                $objs[] = new AddressBook(
                    $this->carddavBackend,
                    $addressbook
                );
            }
        }
        $SharedContactsModule = \Aurora\System\Api::GetModule('SharedContacts');
        if ($SharedContactsModule && !$SharedContactsModule->getConfig('Disabled', true)) {
            if ($this->carddavBackend instanceof Backend\PDO) {

                $sharedWithAllAddressbook = $this->carddavBackend->getSharedWithAllAddressBook($this->principalUri);
                $objs[] = new SharedWithAll\AddressBook(
                    $this->carddavBackend,
                    $sharedWithAllAddressbook,
                    $this->principalUri
                );

                $sharedAddressbooks = $this->carddavBackend->getSharedAddressBooks($this->principalUri);
                foreach ($sharedAddressbooks as $sharedAddressbook) {
                    if ($sharedAddressbook['principaluri'] != $this->principalUri) {
                        if (count($objs) > 0) {
                            foreach ($objs as $key => $val) {
                                if ($val instanceof Shared\AddressBook) {
                                    $props = $val->getProperties(['id', 'access' ,'group_id']);
                                    if ($props['id'] === $sharedAddressbook['id']) { // personal sharing
                                        if ($sharedAddressbook['group_id'] == 0) {
                                            $objs[$key]->setAccess((int) $sharedAddressbook['access']);
                                        } else { //group sharing
                                            if ($sharedAddressbook['access'] != Access::Read) {
                                                if ((int) $props['access'] > (int) $sharedAddressbook['access'] || (int) $sharedAddressbook['access'] === Access::NoAccess) {
                                                    $objs[$key]->setAccess((int) $sharedAddressbook['access']);
                                                }
                                            } elseif ($props['access'] != Access::Write) {
                                                $objs[$key]->setAccess((int) $sharedAddressbook['access']);
                                            }
                                        }
                                        continue 2;
                                    }
                                }
                            }
                        }

                        $objs[] = new Shared\AddressBook(
                            $this->carddavBackend,
                            $sharedAddressbook,
                            $this->principalUri
                        );
                    }
                }
            }
            $objs = array_filter($objs, function ($obj) {
                return !($obj instanceof Shared\AddressBook) || ($obj instanceof Shared\AddressBook && $obj->getAccess() != Access::NoAccess);
            });
        }
        return $objs;
    }
}
