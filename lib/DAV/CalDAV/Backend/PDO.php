<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV\Backend;

use Afterlogic\DAV\Constants;
use Sabre\DAV\Xml\Element\Sharee;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class PDO extends \Sabre\CalDAV\Backend\PDO
{
    /**
     * @var string
     */
    protected $dBPrefix;

    /**
     * Creates the backend
     */
    public function __construct()
    {
        parent::__construct(\Aurora\System\Api::GetPDO());

        $this->dBPrefix = \Aurora\System\Api::GetSettings()->DBPrefix;

        $this->calendarTableName = $this->dBPrefix.Constants::T_CALENDARS;
        $this->calendarChangesTableName = $this->dBPrefix.Constants::T_CALENDARCHANGES;
        $this->calendarObjectTableName = $this->dBPrefix . Constants::T_CALENDAROBJECTS;
        $this->schedulingObjectTableName = $this->dBPrefix . Constants::T_SCHEDULINGOBJECTS;
        $this->calendarSubscriptionsTableName = $this->dBPrefix . Constants::T_CALENDARSUBSCRIPTIONS;
        $this->calendarInstancesTableName = $this->dBPrefix . Constants::T_CALENDARINSTANCES;
    }

    public function createCalendar($principalUri, $calendarUri, array $properties)
    {
        $sOrderProp = '{http://apple.com/ns/ical/}calendar-order';
        if (!isset($properties[$sOrderProp])) {
            $properties[$sOrderProp] = 1;
        }

        return parent::createCalendar($principalUri, $calendarUri, $properties);
    }

    public function deletePrincipalCalendars($principalUri)
    {
        $stmt = $this->pdo->prepare(
            <<<SQL
SELECT {$this->calendarInstancesTableName}.calendarid, {$this->calendarInstancesTableName}.id FROM {$this->calendarInstancesTableName}
WHERE principaluri = ?
SQL
        );
        $stmt->execute([$principalUri]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $this->deleteCalendar([$row['calendarid'], $row['id']]);
        }
    }

    public function deleteSubscriptionsByPrincipal($principalUri)
    {
        $stmt = $this->pdo->prepare(
            <<<SQL
		DELETE FROM {$this->calendarSubscriptionsTableName}
		WHERE principaluri = ?
		SQL
        );
        $stmt->execute([$principalUri]);
    }

    protected function getTenantPrincipal($sUserPublicId)
    {
        $sTenantPrincipal = 'default_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
        $oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
        if ($oUser) {
            $sTenantPrincipal = $oUser->IdTenant . '_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
        }

        return \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $sTenantPrincipal;
    }

    public function getPublicCalendar($calendarId)
    {
        $calendar = false;

        $fields = array_values($this->propertyMap);
        array_push($fields, 'calendarid', 'uri', 'synctoken', 'components', 'principaluri', 'transparent', 'access');

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(
            <<<SQL
SELECT {$this->calendarInstancesTableName}.id as id, $fields FROM {$this->calendarInstancesTableName}
    LEFT JOIN {$this->calendarTableName} ON
        {$this->calendarInstancesTableName}.calendarid = {$this->calendarTableName}.id
WHERE access = 1 AND {$this->calendarInstancesTableName}.uri = ? AND public = 1 ORDER BY calendarorder ASC
SQL
        );

        $stmt->execute([$calendarId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row) {
            $components = [];
            if ($row['components']) {
                $components = explode(',', $row['components']);
            }

            $calendar = [
                'id'                                                                 => [(int)$row['calendarid'], (int)$row['id']],
                'uri'                                                                => $row['uri'],
                'principaluri'                                                       => $row['principaluri'],
                '{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag'                  => 'http://sabre.io/ns/sync/' . ($row['synctoken'] ? $row['synctoken'] : '0'),
                '{http://sabredav.org/ns}sync-token'                                 => $row['synctoken'] ? $row['synctoken'] : '0',
                '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet($components),
                '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp'         => new \Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp($row['transparent'] ? 'transparent' : 'opaque'),
                'share-resource-uri'                                                 => '/ns/share/' . $row['calendarid'],
            ];

            $row['access'] = \Sabre\DAV\Sharing\Plugin::ACCESS_READ;
            $calendar['share-access'] = (int)$row['access'];
            // 1 = owner, 2 = readonly, 3 = readwrite
            if ($row['access'] > 1) {
                // We need to find more information about the original owner.
                //$stmt2 = $this->pdo->prepare('SELECT principaluri FROM ' . $this->calendarInstancesTableName . ' WHERE access = 1 AND id = ?');
                //$stmt2->execute([$row['id']]);

                // read-only is for backwards compatbility. Might go away in
                // the future.
                $calendar['read-only'] = (int)$row['access'] === \Sabre\DAV\Sharing\Plugin::ACCESS_READ;
            }

            foreach ($this->propertyMap as $xmlName => $dbName) {
                $calendar[$xmlName] = $row[$dbName];
            }
        }

        return $calendar;
    }

    public function getParentCalendar($calendarId)
    {
        $fields = array_values($this->propertyMap);
        $fields[] = 'calendarid';
        $fields[] = 'uri';
        $fields[] = 'synctoken';
        $fields[] = 'components';
        $fields[] = 'principaluri';
        $fields[] = 'transparent';
        $fields[] = 'access';

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(
            <<<SQL
SELECT {$this->calendarInstancesTableName}.id as id, $fields FROM {$this->calendarInstancesTableName}
    LEFT JOIN {$this->calendarTableName} ON
        {$this->calendarInstancesTableName}.calendarid = {$this->calendarTableName}.id
WHERE access = 1 AND {$this->calendarInstancesTableName}.calendarid = ? ORDER BY calendarorder ASC
SQL
        );

        $stmt->execute([$calendarId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $components = [];
        if ($row['components']) {
            $components = explode(',', $row['components']);
        }

        $calendar = [
            'id'                                                                 => [(int)$row['calendarid'], (int)$row['id']],
            'uri'                                                                => $row['uri'],
            'principaluri'                                                       => $row['principaluri'],
            '{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag'                  => 'http://sabre.io/ns/sync/' . ($row['synctoken'] ? $row['synctoken'] : '0'),
            '{http://sabredav.org/ns}sync-token'                                 => $row['synctoken'] ? $row['synctoken'] : '0',
            '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet($components),
            '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp'         => new \Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp($row['transparent'] ? 'transparent' : 'opaque'),
            'share-resource-uri'                                                 => '/ns/share/' . $row['calendarid'],
        ];

        $calendar['share-access'] = (int)$row['access'];
        // 1 = owner, 2 = readonly, 3 = readwrite
        if ($row['access'] > 1) {
            // We need to find more information about the original owner.
            //$stmt2 = $this->pdo->prepare('SELECT principaluri FROM ' . $this->calendarInstancesTableName . ' WHERE access = 1 AND id = ?');
            //$stmt2->execute([$row['id']]);

            // read-only is for backwards compatbility. Might go away in
            // the future.
            $calendar['read-only'] = (int)$row['access'] === \Sabre\DAV\Sharing\Plugin::ACCESS_READ;
        }

        foreach ($this->propertyMap as $xmlName => $dbName) {
            $calendar[$xmlName] = $row[$dbName];
        }


        return $calendar;
    }

    /**
     * This method is called when a user replied to a request to share.
     *
     * If the user chose to accept the share, this method should return the
     * newly created calendar url.
     *
     * @param string href The sharee who is replying (often a mailto: address)
     * @param int status One of the SharingPlugin::STATUS_* constants
     * @param string $calendarUri The url to the calendar thats being shared
     * @param string $inReplyTo The unique id this message is a response to
     * @param string $summary A description of the reply
     * @return null|string
     */
    public function shareReply($href, $status, $calendarUri, $inReplyTo, $summary = null)
    {
    }

    /**
     * Marks this calendar as published.
     *
     * Publishing a calendar should automatically create a read-only, public,
     * subscribable calendar.
     *
     * @param bool $value
     * @return bool
     */
    public function setPublishStatus($calendarUri, $value, $oUser = null)
    {
        $bResult = false;
        $oUser = $oUser ? $oUser : \Aurora\System\Api::getAuthenticatedUser();
        if ($oUser) {
            $stmt = $this->pdo->prepare('UPDATE ' . $this->calendarInstancesTableName . ' SET `public` = ? WHERE principaluri = ? AND uri = ?');
            $bResult =  $stmt->execute([(int)$value, \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $oUser->PublicId, $calendarUri]);
        }

        return $bResult;
    }

    /**
     * Marks this calendar as published.
     *
     * Publishing a calendar should automatically create a read-only, public,
     * subscribable calendar.
     *
     * @return bool
     */
    public function getPublishStatus($calendarUri)
    {
        $bResult = false;
        $oUser = \Aurora\System\Api::getAuthenticatedUser();
        if ($oUser) {
            $stmt = $this->pdo->prepare('SELECT public FROM ' . $this->calendarInstancesTableName . ' WHERE principaluri = ? AND uri = ?');
            $stmt->execute([\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $oUser->PublicId, $calendarUri]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $bResult = (bool) $row['public'];
            }
        }

        return $bResult;
    }

    /**
     * Returns a list of notifications for a given principal url.
     *
     * The returned array should only consist of implementations of
     * \Sabre\CalDAV\Notifications\INotificationType.
     *
     * @param string $principalUri
     * @return array
     */
    public function getNotificationsForPrincipal($principalUri)
    {
        $aNotifications = array();
        /*
                // get ALL notifications for the user NB. Any read or out of date notifications should be already deleted.
                $stmt = $this->pdo->prepare("SELECT * FROM ".$this->notificationsTableName." WHERE principaluri = ? ORDER BY dtstamp ASC");
                $stmt->execute(array($principalUri));

                while($aRow = $stmt->fetch(\PDO::FETCH_ASSOC))
                {
                    // we need to return the correct type of notification
                    switch($aRow['notification'])
                    {
                        case 'Invite':
                            $aValues = array();
                            // sort out the required data
                            if($aRow['id'])
                            {
                                $aValues['id'] = $aRow['id'];
                            }
                            if($aRow['etag'])
                            {
                                $aValues['etag'] = $aRow['etag'];
                            }
                            if($aRow['principaluri'])
                            {
                                $aValues['href'] = $aRow['principaluri'];
                            }
                            if($aRow['dtstamp'])
                            {
                                $aValues['dtstamp'] = $aRow['dtstamp'];
                            }
                            if($aRow['type'])
                            {
                                $aValues['type'] = $aRow['type'];
                            }
                            if($aRow['readonly'])
                            {
                                $aValues['readOnly'] = $aRow['readonly'];
                            }
                            if($aRow['hosturl'])
                            {
                                $aValues['hosturl'] = $aRow['hosturl'];
                            }
                            if($aRow['organizer'])
                            {
                                $aValues['organizer'] = $aRow['organizer'];
                            }
                            if($aRow['commonname'])
                            {
                                $aValues['commonName'] = $aRow['commonname'];
                            }
                            if($aRow['firstname'])
                            {
                                $aValues['firstname'] = $aRow['firstname'];
                            }
                            if($aRow['lastname'])
                            {
                                $aValues['lastname'] = $aRow['lastname'];
                            }
                            if($aRow['summary'])
                            {
                                $aValues['summary'] = $aRow['summary'];
                            }

                            $aNotifications[] = new \Sabre\CalDAV\Notifications\Notification\Invite($aValues);
                            break;

                        case 'InviteReply':
                            break;
                        case 'SystemStatus':
                            break;
                    }

                }
        */
        return $aNotifications;
    }

    /**
     * This deletes a specific notifcation.
     *
     * This may be called by a client once it deems a notification handled.
     *
     * @param string $sPrincipalUri
     * @param \Sabre\CalDAV\Notifications\INotificationType $oNotification
     * @return void
     */
    public function deleteNotification($sPrincipalUri, \Sabre\CalDAV\Xml\Notification\NotificationInterface $oNotification)
    {
    }

    /**
     * Returns a list of calendar instances for calendar.
     *
     *
     * @param string $calendarId
     * @return array
     */
    public function getCalendarInstances($calendarId)
    {
        $fields = array_values($this->propertyMap);
        $fields[] = 'calendarid';
        $fields[] = 'uri';
        $fields[] = 'synctoken';
        $fields[] = 'components';
        $fields[] = 'principaluri';
        $fields[] = 'transparent';
        $fields[] = 'access';

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(
            <<<SQL
SELECT {$this->calendarInstancesTableName}.id as id, $fields FROM {$this->calendarInstancesTableName}
    LEFT JOIN {$this->calendarTableName} ON
        {$this->calendarInstancesTableName}.calendarid = {$this->calendarTableName}.id
WHERE calendarid = ? ORDER BY calendarorder ASC
SQL
        );
        $stmt->execute([$calendarId]);

        $calendars = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $components = [];
            if ($row['components']) {
                $components = explode(',', $row['components']);
            }

            $calendar = [
                'id'                                                                 => [(int)$row['calendarid'], (int)$row['id']],
                'uri'                                                                => $row['uri'],
                'principaluri'                                                       => $row['principaluri'],
                '{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag'                  => 'http://sabre.io/ns/sync/' . ($row['synctoken'] ? $row['synctoken'] : '0'),
                '{http://sabredav.org/ns}sync-token'                                 => $row['synctoken'] ? $row['synctoken'] : '0',
                '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet($components),
                '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp'         => new \Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp($row['transparent'] ? 'transparent' : 'opaque'),
                'share-resource-uri'                                                 => '/ns/share/' . $row['calendarid'],
            ];

            $calendar['share-access'] = (int)$row['access'];
            // 1 = owner, 2 = readonly, 3 = readwrite
            if ($row['access'] > 1) {
                // We need to find more information about the original owner.
                //$stmt2 = $this->pdo->prepare('SELECT principaluri FROM ' . $this->calendarInstancesTableName . ' WHERE access = 1 AND id = ?');
                //$stmt2->execute([$row['id']]);

                // read-only is for backwards compatbility. Might go away in
                // the future.
                $calendar['read-only'] = (int)$row['access'] === \Sabre\DAV\Sharing\Plugin::ACCESS_READ;
            }

            foreach ($this->propertyMap as $xmlName => $dbName) {
                $calendar[$xmlName] = $row[$dbName];
            }

            $calendars[] = $calendar;
        }

        return $calendars;
    }

    /**
     * Returns the list of people whom a calendar is shared with.
     *
     * Every item in the returned list must be a Sharee object with at
     * least the following properties set:
     *   $href
     *   $shareAccess
     *   $inviteStatus
     *
     * and optionally:
     *   $properties
     *
     * @param mixed $calendarId
     *
     * @return \Sabre\DAV\Xml\Element\Sharee[]
     */
    public function getInvites($calendarId)
    {
        if (!is_array($calendarId)) {
            throw new \InvalidArgumentException('The value passed to getInvites() is expected to be an array with a calendarId and an instanceId');
        }
        list($calendarId, $instanceId) = $calendarId;

        $query = <<<SQL
SELECT
    principaluri,
    access,
    share_href,
    share_displayname,
    share_invitestatus
FROM {$this->calendarInstancesTableName}
WHERE
    calendarid = ?
SQL;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$calendarId]);

        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = new Sharee([
                'href' => isset($row['share_href']) ? $row['share_href'] : 'mailto:' . \Sabre\HTTP\encodePath(basename($row['principaluri'])),
                'access' => (int) $row['access'],
                /// Everyone is always immediately accepted, for now.
                'inviteStatus' => (int) $row['share_invitestatus'],
                'properties' => !empty($row['share_displayname'])
                    ? ['{DAV:}displayname' => $row['share_displayname']]
                    : [],
                'principal' => $row['principaluri'],
            ]);
        }

        return $result;
    }

    /**
     * Parses some information from calendar objects, used for optimized
     * calendar-queries.
     *
     * Returns an array with the following keys:
     *   * etag - An md5 checksum of the object without the quotes.
     *   * size - Size of the object in bytes
     *   * componentType - VEVENT, VTODO or VJOURNAL
     *   * firstOccurence
     *   * lastOccurence
     *   * uid - value of the UID property
     *
     * @param string $calendarData
     *
     * @return array
     */
    protected function getDenormalizedData($calendarData)
    {
        $vObject = \Sabre\VObject\Reader::read($calendarData, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
        $componentType = null;
        $component = null;
        $firstOccurence = null;
        $lastOccurence = null;
        $uid = null;
        foreach ($vObject->getComponents() as $component) {
            if ('VTIMEZONE' !== $component->name) {
                $componentType = $component->name;
                $uid = (string) $component->UID;
                break;
            }
        }
        if (!$componentType) {
            throw new \Sabre\DAV\Exception\BadRequest('Calendar objects must have a VJOURNAL, VEVENT or VTODO component');
        }
        if ('VEVENT' === $componentType) {
            $firstOccurence = $component->DTSTART->getDateTime()->getTimeStamp();
            // Finding the last occurence is a bit harder
            if (!isset($component->RRULE)) {
                if (isset($component->DTEND)) {
                    $lastOccurence = $component->DTEND->getDateTime()->getTimeStamp();
                } elseif (isset($component->DURATION)) {
                    $endDate = clone $component->DTSTART->getDateTime();
                    $endDate = $endDate->add(\Sabre\VObject\DateTimeParser::parse($component->DURATION->getValue()));
                    $lastOccurence = $endDate->getTimeStamp();
                } elseif (!$component->DTSTART->hasTime()) {
                    $endDate = clone $component->DTSTART->getDateTime();
                    $endDate = $endDate->modify('+1 day');
                    $lastOccurence = $endDate->getTimeStamp();
                } else {
                    $lastOccurence = $firstOccurence;
                }
            } else {
                $it = new \Sabre\VObject\Recur\EventIterator($vObject, (string) $component->UID);
                $maxDate = new \DateTime(self::MAX_DATE);
                if ($it->isInfinite()) {
                    $lastOccurence = $maxDate->getTimeStamp();
                } else {
                    $end = $it->getDtEnd();
                    while ($it->valid() && $end < $maxDate) {
                        $end = $it->getDtEnd();
                        $it->next();
                    }
                    $lastOccurence = $end->getTimeStamp();
                }
            }

            // Ensure Occurence values are positive
            if ($firstOccurence < 0) {
                $firstOccurence = 0;
            }
            if ($lastOccurence < 0) {
                $lastOccurence = 0;
            }
        }

        // Destroy circular references to PHP will GC the object.
        $vObject->destroy();

        return [
            'etag' => md5($calendarData),
            'size' => strlen($calendarData),
            'componentType' => $componentType,
            'firstOccurence' => $firstOccurence,
            'lastOccurence' => $lastOccurence,
            'uid' => $uid,
        ];
    }
}
