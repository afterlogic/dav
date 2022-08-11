<?php

namespace Afterlogic\DAV\CardDAV;

use Afterlogic\DAV\Server;
use Sabre\DAV;
use Sabre\DAV\MkCol;
use Sabre\DAVACL;
class AddressBookRoot extends DAV\Collection implements DAV\IExtendedCollection, DAVACL\IACL
{
    use DAVACL\ACLTrait;

    protected $addressbookHome = null;

    function __construct(\Sabre\CardDAV\Backend\BackendInterface $carddavBackend) {
        $principalInfo = Server::getCurrentPrincipalInfo();
        $this->addressbookHome = new AddressBookHome($carddavBackend, $principalInfo['uri']);
    }

    /**
     * Returns the name of this object.
     *
     * @return string
     */
    public function getName()
    {
        return $this->addressbookHome->getName();
    }

    /**
     * Updates the name of this object.
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->addressbookHome->setName($name);
    }

    /**
     * Deletes this object.
     */
    public function delete()
    {
        $this->addressbookHome->delete();
    }

    /**
     * Returns the last modification date.
     *
     * @return int
     */
    public function getLastModified()
    {
        return $this->addressbookHome->getLastModified();
    }

    /**
     * Creates a new file under this object.
     *
     * This is currently not allowed
     *
     * @param string   $filename
     * @param resource $data
     */
    public function createFile($filename, $data = null)
    {
        $this->addressbookHome->createFile($filename, $data);
    }

    /**
     * Creates a new directory under this object.
     *
     * This is currently not allowed.
     *
     * @param string $filename
     */
    public function createDirectory($filename)
    {
        $this->addressbookHome->createDirectory($filename);
    }

    /**
     * Returns a single addressbook, by name.
     *
     * @param string $name
     *
     * @todo needs optimizing
     *
     * @return AddressBook
     */
    public function getChild($name)
    {
        return $this->addressbookHome->getChild($name);
    }

    /**
     * Returns a list of addressbooks.
     *
     * @return array
     */
    public function getChildren()
    {
        return $this->addressbookHome->getChildren();
    }

    /**
     * Creates a new address book.
     *
     * @param string $name
     *
     * @throws DAV\Exception\InvalidResourceType
     */
    public function createExtendedCollection($name, MkCol $mkCol)
    {
        $this->addressbookHome->createExtendedCollection($name, $mkCol);
    }

    /**
     * Returns the owner principal.
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
    public function getOwner()
    {
        return $this->addressbookHome->getOwner();
    }

    public function getACL() 
    {
        return $this->addressbookHome->getACL();
    }
}