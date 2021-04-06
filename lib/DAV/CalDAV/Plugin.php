<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
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

    protected function fixOrganizer(&$data, &$modified)
    {
        // If it's a stream, we convert it to a string first.
        if (is_resource($data)) {
            $data = stream_get_contents($data);
        }

        $vobj = \Sabre\VObject\Reader::read($data);
        if (isset($vobj->VEVENT->ORGANIZER))
        {
            $sOrganizer = $vobj->VEVENT->ORGANIZER->getNormalizedValue();
            $iPos = strpos($sOrganizer, 'principals/');
            if ($iPos !== false)
            {
                $sOrganizer = 'mailto:' . \trim(substr($sOrganizer, $iPos + 11), '/');
                $vobj->VEVENT->ORGANIZER->setValue($sOrganizer);
                $data = $vobj->serialize();
                $vobj->destroy();
                $modified = true;
            }
        }
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

        if (!$node instanceof \Sabre\CalDAV\ICalendarObject)
            return;

        // We're onyl interested in ICalendarObject nodes that are inside of a
        // real calendar. This is to avoid triggering validation and scheduling
        // for non-calendars (such as an inbox).
        list($parent) =  \Sabre\Uri\split($path);
        $parentNode = $this->server->tree->getNodeForPath($parent);

        if (!$parentNode instanceof \Sabre\CalDAV\ICalendar)
            return;

        $this->fixOrganizer($data, $modified);
        try{
            $this->validateICalendar(
                $data,
                $path,
                $modified,
                $this->server->httpRequest,
                $this->server->httpResponse,
                false
            );
        } catch (\Exception $oEx) {}
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

        if (!$parentNode instanceof \Sabre\CalDAV\ICalendar)
            return;

        $this->fixOrganizer($data, $modified);
        try{
            $this->validateICalendar(
                $data,
                $path,
                $modified,
                $this->server->httpRequest,
                $this->server->httpResponse,
                false
            );
        } catch (\Exception $oEx) {}
    }

   /**
     * Use this method to tell the server this plugin defines additional
     * HTTP methods.
     *
     * This method is passed a uri. It should only return HTTP methods that are
     * available for the specified uri.
     *
     * @param string $uri
     *
     * @return array
     */
    public function getHTTPMethods($uri)
    {
        $uri = '/'.\ltrim($uri, '/');
        // The MKCALENDAR is only available on unmapped uri's, whose
        // parents extend IExtendedCollection
        list($parent, $name) = \Sabre\Uri\split($uri);
        if (isset($parent))
        {
            $node = $this->server->tree->getNodeForPath($parent);

            if ($node instanceof \Sabre\DAV\IExtendedCollection) {
                try {
                    $node->getChild($name);
                } catch (\Sabre\DAV\Exception\NotFound $e) {
                    return ['MKCALENDAR'];
                }
            }
        }

        return [];
    }
}
