<?php

namespace Afterlogic\DAV\CalDAV;

/**
 * This object represents a CalDAV calendar.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Calendar extends \Sabre\CalDAV\Calendar {

    use CalendarTrait;

    public function getProperties($requestedProperties)
    {
        return $this->_getProperties($this->calendarInfo, $requestedProperties);
    }

}
