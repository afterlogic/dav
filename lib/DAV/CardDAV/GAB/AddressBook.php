<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV\GAB;

use Afterlogic\DAV\Backend;
use Aurora\Modules\Contacts\Models\Contact;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class AddressBook extends \Afterlogic\DAV\CardDAV\AddressBook
{
    /**
     * @var array
     */
    protected $addressBookInfo;

    /**
     * @var string
     */
    private $sUserPublicId;

    /**
     * Constructor
     */ 
    public function __construct($caldavBackend)
    {
        $this->carddavBackend = $caldavBackend;
    }


    public function getUser()
    {
        if ($this->sUserPublicId == null) {
            $this->sUserPublicId = \Afterlogic\DAV\Server::getUser();
        }
        return $this->sUserPublicId;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'gab';
    }

    protected function initAddressbook()
    {
        $oUser = \Afterlogic\DAV\Server::getUserObject();
        if ($oUser) {
            $sPrincipalUri = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $oUser->IdTenant . '_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
            $addressbook = Backend::Carddav()->getAddressBookForUser($sPrincipalUri, 'gab');
            if ($addressbook) {
                $this->addressBookInfo = $addressbook;
            }
        }
    }

    public function getChildren()
    {
        $this->initAddressbook();
        return parent::getChildren();
    }

    public function getChild($name)
    {
        $this->initAddressbook();
        return parent::getChild($name);
    }

    /* @param array $mutations
     * @return bool|array
     */
    public function updateProperties($mutations)
    {
        return false;
    }

    public function propPatch(\Sabre\DAV\PropPatch $propPatch)
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
        $sUserPublicId = $this->getUser();
        return ($sUserPublicId) ? 'principals/' . $sUserPublicId : null;
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
        $sUserPublicId = $this->getUser();
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
        $oTenant = \Afterlogic\DAV\Server::getTenantObject();
        $oUser = \Afterlogic\DAV\Server::getUserObject();
        $bIsModuleDisabledForTenant = isset($oTenant) ? $oTenant->isModuleDisabled('TeamContacts') : false;
        $bIsModuleDisabledForUser = isset($oUser) ? $oUser->isModuleDisabled('TeamContacts') : false;

        return !($bIsModuleDisabledForTenant || $bIsModuleDisabledForUser);
    }
}
