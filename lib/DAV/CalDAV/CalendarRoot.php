<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CalDAV;

class CalendarRoot extends \Sabre\CalDAV\CalendarRoot{

    /**
     * This method returns a node for a principal.
     *
     * The passed array contains principal information, and is guaranteed to
     * at least contain a uri item. Other properties may or may not be
     * supplied by the authentication backend.
     *
     * @param array $principal
     * @return \Sabre\DAV\INode
     */
    public function getChildForPrincipal(array $principal) {

        return new UserCalendars($this->caldavBackend, $principal);

    }

}
