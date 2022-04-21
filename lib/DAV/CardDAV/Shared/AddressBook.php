<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV\Shared;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class AddressBook extends \Afterlogic\DAV\CardDAV\AddressBook {

    use AddressbookTrait;

    public function __construct(\Sabre\CardDAV\Backend\BackendInterface $carddavBackend, array $addressBookInfo, $principalUri) {
		parent::__construct($carddavBackend, $addressBookInfo);
		$this->principalUri = $principalUri;

    }

    function getOwner() {
        return $this->addressBookInfo['principaluri'];
    }

    function getACL() {

        $acl = [
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->principalUri,
                'protected' => true,
            ],
        ];

        if ($this->addressBookInfo['access'] == 2) {
            $acl[] = [
                'privilege' => '{DAV:}write',
                'principal' => $this->principalUri,
                'protected' => true,
            ];
        }

        return $acl;

    }
}
