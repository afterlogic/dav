<?php

namespace Afterlogic\DAV\CalDAV;

/**
 * This object represents a CalDAV calendar that is shared by a different user.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class SharedWithAllCalendar extends \Sabre\CalDAV\SharedCalendar {

    /**
     * Returns a list of ACE's for this node.
     *
     * Each ACE has the following properties:
     *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
     *     currently the only supported privileges
     *   * 'principal', a url to the principal who owns the node
     *   * 'protected' (optional), indicating that this ACE is not allowed to
     *      be updated.
     *
     * @return array
     */
    function getACL() {

        $acl = [];
		
		$sPrincipalUri = $this->calendarInfo['principaluri'];
		
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser)
		{
			$sPrincipalUri = 'principals/' . $oUser->PublicId;
		}

        switch ($this->getShareAccess()) {
			case \Sabre\DAV\Sharing\Plugin::ACCESS_NOTSHARED :
            case \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER :
                $acl[] = [
                    'privilege' => '{DAV:}share',
                    'principal' => $sPrincipalUri,
                    'protected' => true,
                ];
                $acl[] = [
                    'privilege' => '{DAV:}share',
                    'principal' => $sPrincipalUri . '/calendar-proxy-write',
                    'protected' => true,
                ];
                // No break intentional!
            case \Sabre\DAV\Sharing\Plugin::ACCESS_READWRITE :
                $acl[] = [
                    'privilege' => '{DAV:}write',
                    'principal' => $sPrincipalUri,
                    'protected' => true,
                ];
                $acl[] = [
                    'privilege' => '{DAV:}write',
                    'principal' => $sPrincipalUri . '/calendar-proxy-write',
                    'protected' => true,
                ];
                // No break intentional!
            case \Sabre\DAV\Sharing\Plugin::ACCESS_READ :
                $acl[] = [
                    'privilege' => '{DAV:}write-properties',
                    'principal' => $sPrincipalUri,
                    'protected' => true,
                ];
                $acl[] = [
                    'privilege' => '{DAV:}write-properties',
                    'principal' => $sPrincipalUri . '/calendar-proxy-write',
                    'protected' => true,
                ];
                $acl[] = [
                    'privilege' => '{DAV:}read',
                    'principal' => $sPrincipalUri,
                    'protected' => true,
                ];
                $acl[] = [
                    'privilege' => '{DAV:}read',
                    'principal' => $sPrincipalUri . '/calendar-proxy-read',
                    'protected' => true,
                ];
                $acl[] = [
                    'privilege' => '{DAV:}read',
                    'principal' => $sPrincipalUri . '/calendar-proxy-write',
                    'protected' => true,
                ];
                $acl[] = [
                    'privilege' => '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}read-free-busy',
                    'principal' => '{DAV:}authenticated',
                    'protected' => true,
                ];
                break;
        }
        return $acl;

    }


    /**
     * This method returns the ACL's for calendar objects in this calendar.
     * The result of this method automatically gets passed to the
     * calendar-object nodes in the calendar.
     *
     * @return array
     */
    function getChildACL() {

        $acl = [];

		$sPrincipalUri = $this->calendarInfo['principaluri'];

		switch ($this->getShareAccess()) {
            case \Sabre\DAV\Sharing\Plugin::ACCESS_NOTSHARED :
                // No break intentional
            case \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER :
                // No break intentional
            case \Sabre\DAV\Sharing\Plugin::ACCESS_READWRITE:
                $acl[] = [
                    'privilege' => '{DAV:}write',
                    'principal' => $sPrincipalUri,
                    'protected' => true,
                ];
                $acl[] = [
                    'privilege' => '{DAV:}write',
                    'principal' => $sPrincipalUri . '/calendar-proxy-write',
                    'protected' => true,
                ];
                // No break intentional
            case \Sabre\DAV\Sharing\Plugin::ACCESS_READ:
                $acl[] = [
                    'privilege' => '{DAV:}read',
                    'principal' => $sPrincipalUri,
                    'protected' => true,
                ];
                $acl[] = [
                    'privilege' => '{DAV:}read',
                    'principal' => $sPrincipalUri . '/calendar-proxy-write',
                    'protected' => true,
                ];
                $acl[] = [
                    'privilege' => '{DAV:}read',
                    'principal' => $sPrincipalUri . '/calendar-proxy-read',
                    'protected' => true,
                ];
                break;
        }

        return $acl;

    }

}
