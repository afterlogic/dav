<?php

namespace Afterlogic\DAV\CalDAV;

class Plugin extends \Sabre\CalDAV\Plugin {

    /**
     * Returns the path to a principal's calendar home.
     *
     * The return url must not end with a slash.
     * This function should return null in case a principal did not have
     * a calendar home.
     *
     * @param string $principalUrl
     * @return string
     */
    function getCalendarHomeForPrincipal($principalUrl) {

        return self::CALENDAR_ROOT;

    }
}
