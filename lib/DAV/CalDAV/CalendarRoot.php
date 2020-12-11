<?php

namespace Afterlogic\DAV\CalDAV;

use Sabre\DAVACL\PrincipalBackend;

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
class CalendarRoot extends \Sabre\CalDAV\CalendarRoot {

    function __construct() {

        parent::__construct(\Afterlogic\DAV\Backend::Principal(), \Afterlogic\DAV\Backend::CalDAV());

    }

    // /**
    //  * This method returns a node for a principal.
    //  *
    //  * The passed array contains principal information, and is guaranteed to
    //  * at least contain a uri item. Other properties may or may not be
    //  * supplied by the authentication backend.
    //  *
    //  * @param array $principal
    //  * @return \Sabre\DAV\INode
    //  */
    // function getChildForPrincipal(array $principal) {

    //     return new CalendarHome($this->caldavBackend, $principal);

    // }

}
