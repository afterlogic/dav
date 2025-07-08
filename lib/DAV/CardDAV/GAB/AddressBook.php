<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV\GAB;

use Afterlogic\DAV\Server;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class AddressBook extends \Afterlogic\DAV\CardDAV\AddressBook
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'gab';
    }

    /**
     * Returns a card.
     *
     * @param string $name
     *
     * @return Card
     */
    public function getChild($name)
    {
        $obj = $this->carddavBackend->getCard($this->addressBookInfo['id'], $name);
        if (!$obj) {
            throw new \Sabre\DAV\Exception\NotFound('Card not found');
        }

        return new Card($this->carddavBackend, $this->addressBookInfo, $obj);
    }

    /**
     * Returns the full list of cards.
     *
     * @return array
     */
    public function getChildren()
    {
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
     *
     * @return array
     */
    public function getMultipleChildren(array $paths)
    {
        $objs = $this->carddavBackend->getMultipleCards($this->addressBookInfo['id'], $paths);
        $children = [];
        foreach ($objs as $obj) {
            $obj['acl'] = $this->getChildACL();
            $children[] = new Card($this->carddavBackend, $this->addressBookInfo, $obj);
        }

        return $children;
    }

    /* @param array $mutations
     *
     * @return bool|array
     */
    public function updateProperties($mutations)
    {
        return false;
    }

    /**
     * Returns the owner principal
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
    public function getOwner()
    {
        $sUserPublicId = Server::getUser();
        return $sUserPublicId ? 'principals/' . $sUserPublicId : null;
    }

    /**
     * Returns a group principal
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
    public function getGroup()
    {
        return null;
    }

    /**
     * Returns a list of ACE's for this node.
     *
     * Each ACE has the following properties:
     *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
     *     currently the only supported privileges
     *   * 'principal', a url to the principal who owns the node
     *   * 'protected' (optional), indicating that this ACE is not allowed to
     *      be updated.
     *
     * @return array
     */
    public function getACL()
    {
        $sUserPublicId = Server::getUser();
        return [
            [
                'privilege' => '{DAV:}read',
                'principal' => ($sUserPublicId) ? 'principals/' . $sUserPublicId : null,
                'protected' => true,
            ],
        ];
    }

    /**
     * Updates the ACL
     *
     * This method will receive a list of new ACE's.
     *
     * @param array $acl
     * @return void
     */
    public function setACL(array $acl)
    {
        throw new \Sabre\DAV\Exception\MethodNotAllowed('Changing ACL is not yet supported');
    }

    /**
     * Returns the list of supported privileges for this node.
     *
     * The returned data structure is a list of nested privileges.
     * See Sabre\DAVACL\Plugin::getDefaultSupportedPrivilegeSet for a simple
     * standard structure.
     *
     * If null is returned from this method, the default privilege set is used,
     * which is fine for most common usecases.
     *
     * @return array|null
     */
    public function getSupportedPrivilegeSet()
    {
        return null;
    }

    public function isEnabled()
    {
        $oTenant = Server::getTenantObject();
        $oUser = Server::getUserObject();
        $bIsModuleDisabledForTenant = isset($oTenant) ? $oTenant->isModuleDisabled('TeamContacts') : false;
        $bIsModuleDisabledForUser = isset($oUser) ? $oUser->isModuleDisabled('TeamContacts') : false;

        return !($bIsModuleDisabledForTenant || $bIsModuleDisabledForUser);
    }

    /**
     * Creates a new file.
     *
     * The contents of the new file must be a valid VCARD.
     *
     * This method may return an ETag.
     *
     * @param string   $name
     * @param resource $data
     *
     * @return string|null
     */
    public function createFile($name, $data = null)
    {
        throw new \Sabre\DAV\Exception\MethodNotAllowed('Create new nodes is not allowed in this addressbook.');
    }

    /**
     * Deletes the entire addressbook.
     */
    public function delete()
    {
        throw new \Sabre\DAV\Exception\MethodNotAllowed('Delete this addressbook is not allowed.');
    }

    /**
     * Updates properties on this node.
     *
     * This method received a PropPatch object, which contains all the
     * information about the update.
     *
     * To update specific properties, call the 'handle' method on this object.
     * Read the PropPatch documentation for more information.
     */
    public function propPatch(\Sabre\DAV\PropPatch $propPatch)
    {
        throw new \Sabre\DAV\Exception\MethodNotAllowed('Updates properties on this addressbook is not allowed.');
    }
}
