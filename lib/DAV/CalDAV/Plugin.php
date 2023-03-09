<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV;

use Sabre\DAV\Xml\Property\LocalHref;
use Sabre\DAVACL;
use Sabre\Uri;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP;
use Sabre\VObject;

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

    /**
     * PropFind.
     *
     * This method handler is invoked before any after properties for a
     * resource are fetched. This allows us to add in any CalDAV specific
     * properties.
     */
    public function propFind(\Sabre\DAV\PropFind $propFind, \Sabre\DAV\INode $node)
    {
        $ns = '{'.self::NS_CALDAV.'}';

        if ($node instanceof \Sabre\CalDAV\ICalendarObjectContainer) {
            $propFind->handle($ns.'max-resource-size', $this->maxResourceSize);
            $propFind->handle($ns.'supported-calendar-data', function () {
                return new \Sabre\CalDAV\Xml\Property\SupportedCalendarData();
            });
            $propFind->handle($ns.'supported-collation-set', function () {
                return new \Sabre\CalDAV\Xml\Property\SupportedCollationSet();
            });
        }

        if ($node instanceof DAVACL\IPrincipal) {
            $principalUrl = $node->getPrincipalUrl();

            $propFind->handle('{'.self::NS_CALDAV.'}calendar-home-set', function () use ($principalUrl) {
                $calendarHomePath = $this->getCalendarHomeForPrincipal($principalUrl);
                if (is_null($calendarHomePath)) {
                    return null;
                }

                return new LocalHref($calendarHomePath.'/');
            });
            // The calendar-user-address-set property is basically mapped to
            // the {DAV:}alternate-URI-set property.
            $propFind->handle('{'.self::NS_CALDAV.'}calendar-user-address-set', function () use ($node) {
                $addresses = $node->getAlternateUriSet();
                $addresses[] = $this->server->getBaseUri().$node->getPrincipalUrl().'/';

                return new LocalHref($addresses);
            });
            // For some reason somebody thought it was a good idea to add
            // another one of these properties. We're supporting it too.
            $propFind->handle('{'.self::NS_CALENDARSERVER.'}email-address-set', function () use ($node) {
                $addresses = $node->getAlternateUriSet();
                $emails = [];
                foreach ($addresses as $address) {
                    if ('mailto:' === substr($address, 0, 7)) {
                        $emails[] = substr($address, 7);
                    }
                }

                return new \Sabre\CalDAV\Xml\Property\EmailAddressSet($emails);
            });

            // These two properties are shortcuts for ical to easily find
            // other principals this principal has access to.
            $propRead = '{'.self::NS_CALENDARSERVER.'}calendar-proxy-read-for';
            $propWrite = '{'.self::NS_CALENDARSERVER.'}calendar-proxy-write-for';

            if (404 === $propFind->getStatus($propRead) || 404 === $propFind->getStatus($propWrite)) {
                $aclPlugin = $this->server->getPlugin('acl');
                $membership = $aclPlugin->getPrincipalMembership($propFind->getPath());
                $readList = [];
                $writeList = [];

                foreach ($membership as $group) {
                    $groupNode = $this->server->tree->getNodeForPath($group);

                    $listItem = Uri\split($group)[0].'/';

                    // If the node is either ap proxy-read or proxy-write
                    // group, we grab the parent principal and add it to the
                    // list.
                    if ($groupNode instanceof \Sabre\CalDAV\Principal\IProxyRead) {
                        $readList[] = $listItem;
                    }
                    if ($groupNode instanceof \Sabre\CalDAV\Principal\IProxyWrite) {
                        $writeList[] = $listItem;
                    }
                }

                $propFind->set($propRead, new LocalHref($readList));
                $propFind->set($propWrite, new LocalHref($writeList));
            }
        } // instanceof IPrincipal

        if ($node instanceof \Sabre\CalDAV\ICalendarObject) {
            // The calendar-data property is not supposed to be a 'real'
            // property, but in large chunks of the spec it does act as such.
            // Therefore we simply expose it as a property.
            $propFind->handle('{'.self::NS_CALDAV.'}calendar-data', function () use ($node) {
                $val = $node->get();
                if (is_resource($val)) {
                    $val = stream_get_contents($val);
                }
                $val = $this->removePrivateInfoFromEvent($node, $val);

                // Taking out \r to not screw up the xml output
                return str_replace("\r", '', $val);
            });
        }
    }

        /**
     * This event is triggered after GET requests.
     *
     * This is used to transform data into jCal, if this was requested.
     */
    public function httpAfterGet(RequestInterface $request, ResponseInterface $response)
    {
        parent::httpAfterGet($request, $response);

        $contentType = $response->getHeader('Content-Type');
        if (null !== $contentType && false !== strpos($contentType, 'text/calendar')) {
            
            $path = $request->getPath();
            $node = $this->server->tree->getNodeForPath($path);
            
            $body = $this->removePrivateInfoFromEvent($node, $response->getBody());
            $response->setBody($body);
            $response->setHeader('Content-Length', strlen($body));
        }
    }

    protected function removePrivateInfoFromEvent($node, $data)
    {
        $result = $data;
        $calendarInfo = (fn() => $this->calendarInfo)->call($node);
        if ($calendarInfo['share-access'] !== \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER) {
            $vobj = VObject\Reader::read($data);
            foreach ($vobj->VEVENT as $key => $event) {
                if ((string) $event->CLASS === 'PRIVATE') {
                    $vobj->VEVENT[$key]->SUBJECT = \Aurora\Api::GetModule('Calendar')->i18N('PRIVATE_SUBJECT');
                    $vobj->VEVENT[$key]->SUMMARY = '';
                    $vobj->VEVENT[$key]->DESCRIPTION = '';
                    $vobj->VEVENT[$key]->LOCATION = '';
                }
            }
            $result = $vobj->serialize();
            // Destroy circular references so PHP will garbage collect the object.
            $vobj->destroy();
        }

        return $result;
    }
    
}
