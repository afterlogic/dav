<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV\Collected;

use Sabre\DAV\Exception\Forbidden;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class AddressBook extends \Afterlogic\DAV\CardDAV\AddressBook {
   /**
     * Returns a card
     *
     * @param string $name
     * @return Card
     */
    function getChild($name) {

        $obj = $this->carddavBackend->getCard($this->addressBookInfo['id'], $name);
        if (!$obj) throw new \Sabre\DAV\Exception\NotFound('Card not found');
        return new Card($this->carddavBackend, $this->addressBookInfo, $obj);

    }

    /**
     * Returns the full list of cards
     *
     * @return array
     */
    function getChildren() {

        $objs = $this->carddavBackend->getCards($this->addressBookInfo['id']);
        $children = [];
        foreach ($objs as $obj) {
            $obj['acl'] = $this->getChildACL();
            $children[] = new Card($this->carddavBackend, $this->addressBookInfo, $obj);
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

        $objs = $this->carddavBackend->getMultipleCards($this->addressBookInfo['id'], $paths);

        $children = [];
        foreach ($objs as $obj) {
            $obj['acl'] = $this->getChildACL();
            $children[] = new Card($this->carddavBackend, $this->addressBookInfo, $obj);
        }
        return $children;

    }
}
