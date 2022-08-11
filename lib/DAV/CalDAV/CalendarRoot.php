<?php

namespace Afterlogic\DAV\CalDAV;

use Afterlogic\DAV\Server;
use Sabre\DAV\MkCol;

/**
 * Calendars collection
 *
 * This object is responsible for generating a list of calendar-homes for each
 * user.
 *
 * This is the top-most node for the calendars tree. In most servers this class
 * represents the "/calendars" path.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class CalendarRoot implements \Sabre\DAV\IExtendedCollection, \Sabre\DAVACL\IACL {

    use  \Sabre\DAVACL\ACLTrait;

    protected $calendarHome = null;

    function __construct(\Sabre\CalDAV\Backend\BackendInterface $caldavBackend) {
        $this->calendarHome = new CalendarHome($caldavBackend, Server::getCurrentPrincipalInfo());
    }

    public function getName() {
        return $this->calendarHome->getName();
    }

    public function setName($name) {
        $this->calendarHome->setName($name);
    }

    public function delete() {
        $this->calendarHome->delete();
    }

    public function getLastModified() {
        return $this->calendarHome->getLastModified();
    }

    public function getChildren() {
        return $this->calendarHome->getChildren();
    }

    public function getChild($name) {
        return $this->calendarHome->getChild($name);
    }

    public function childExists($name) {
        return $this->calendarHome->childExists($name);
    }

    public function createFile($filename, $data = null) {
        $this->calendarHome->createFile($filename, $data);
    }

    public function createDirectory($filename) {
        $this->calendarHome->createDirectory($filename);
    }

    public function createExtendedCollection($name, MkCol $mkCol) {
        $this->calendarHome->createExtendedCollection($name, $mkCol);
    }

    public function getOwner() {
        return $this->calendarHome->getOwner();
    }

    public function getACL() {
        return $this->calendarHome->getACL();
    }

    public function shareReply($href, $status, $calendarUri, $inReplyTo, $summary = null) {
        return $this->calendarHome->shareReply($href, $status, $calendarUri, $inReplyTo, $summary);
    }

    public function getCalendarObjectByUID($uid) {
        return $this->calendarHome->getCalendarObjectByUID($uid);
    }
}
