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
	
    /**
     * This method is triggered before a file gets updated with new content.
     *
     * This plugin uses this method to ensure that CalDAV objects receive
     * valid calendar data.
     *
     * @param string $path
     * @param DAV\IFile $node
     * @param resource $data
     * @param bool $modified Should be set to true, if this event handler
     *                       changed &$data.
     * @return void
     */
    function beforeWriteContent($path, \Sabre\DAV\IFile $node, &$data, &$modified) {

        if (!$node instanceof ICalendarObject)
            return;

        // We're onyl interested in ICalendarObject nodes that are inside of a
        // real calendar. This is to avoid triggering validation and scheduling
        // for non-calendars (such as an inbox).
        list($parent) = Uri\split($path);
        $parentNode = $this->server->tree->getNodeForPath($parent);

        if (!$parentNode instanceof ICalendar)
            return;

// SKIP VALIDATION
/*
        $this->validateICalendar(
            $data,
            $path,
            $modified,
            $this->server->httpRequest,
            $this->server->httpResponse,
            false
        );
*/
    }	
	
    /**
     * This method is triggered before a new file is created.
     *
     * This plugin uses this method to ensure that newly created calendar
     * objects contain valid calendar data.
     *
     * @param string $path
     * @param resource $data
     * @param DAV\ICollection $parentNode
     * @param bool $modified Should be set to true, if this event handler
     *                       changed &$data.
     * @return void
     */
    function beforeCreateFile($path, &$data, \Sabre\DAV\ICollection $parentNode, &$modified) {

        if (!$parentNode instanceof ICalendar)
            return;

// SKIP VALIDATION
/*
        $this->validateICalendar(
            $data,
            $path,
            $modified,
            $this->server->httpRequest,
            $this->server->httpResponse,
            true
        );
 * 
 */

    }	
}
