<?php

namespace Afterlogic\DAV\CalDAV\Shared;

/**
 * This object represents a CalDAV calendar that is shared by a different user.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Calendar extends \Sabre\CalDAV\SharedCalendar {

    use \Afterlogic\DAV\CalDAV\CalendarTrait;

    public function isOwned()
    {
        return $this->getShareAccess() === \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER;
    }
}
