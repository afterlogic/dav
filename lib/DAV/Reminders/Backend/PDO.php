<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\Reminders\Backend;

use Afterlogic\DAV\Constants;
use Aurora\Modules\Calendar\Module;
use Sabre\VObject\Component\VCalendar;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class PDO
{
    /**
     * Reference to PDO connection
     *
     * @var \PDO
     */
    protected $pdo;

    /**
     * PDO table name we'll be using
     *
     * @var string
     */
    protected $table;

    protected $calendarTbl;

    protected $principalsTbl;

    /**
     * Creates the backend object.
     *
     * @return void
     */
    public function __construct()
    {
        $dBPrefix = \Aurora\System\Api::GetSettings()->DBPrefix;

        $this->pdo = \Aurora\System\Api::GetPDO();
        $this->table = $dBPrefix.Constants::T_REMINDERS;
        $this->calendarTbl = $dBPrefix.Constants::T_CALENDARS;
        $this->principalsTbl = $dBPrefix.Constants::T_PRINCIPALS;
    }

    public function getReminder($eventId, $user = null)
    {
        $userWhere = '';
        $params = array($eventId);
        if (isset($user)) {
            $userWhere = ' AND user = ?';
            $params[] = $user;
        }

        $stmt = $this->pdo->prepare('SELECT id, user, calendaruri, eventid, time, starttime, allday'
                . ' FROM '.$this->table.' WHERE eventid = ?'.$userWhere);
        $stmt->execute($params);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getReminders($start = null, $end = null)
    {
        $result = [];
        $values = [];

        $timeFilter = '';
        if ($start != null && $end != null) {
            $timeFilter = ' and time > ? and time <= ?';
            $values = array(
                (int) $start,
                (int) $end
            );
        }

        $stmt = $this->pdo->prepare('SELECT id, user, calendaruri, eventid, time, starttime, allday'
                . ' FROM '.$this->table.' WHERE 1 = 1' . $timeFilter);

        if ($stmt->execute($values)) {
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return $result;
    }

    public function getRemindersForCalendar($user, $calendaruri)
    {
        $result = [];
        $stmt = $this->pdo->prepare('SELECT id, user, calendaruri, eventid, time, starttime, allday'
                . ' FROM '.$this->table.' WHERE user = ? and calendaruri = ?');

        $stmt->execute([$user, $calendaruri]);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = array(
                'id' => $row['id'],
                'user' => $row['user'],
                'calendaruri' => $row['calendaruri'],
                'eventid' => $row['eventid'],
                'time' => $row['time'],
                'starttime' => $row['starttime'],
                'allday' => $row['allday']
                );
        }
        return $result;
    }

    public function addReminder($user, $calendarUri, $eventId, $time = null, $starttime = null, $allday = false)
    {
        $values = $fieldNames = array();
        $fieldNames[] = 'user';
        $values[':user'] = $user;

        $fieldNames[] = 'calendaruri';
        $values[':calendaruri'] = $calendarUri;

        $fieldNames[] = 'eventid';
        $values[':eventid'] = $eventId;

        if ($time != null) {
            $fieldNames[] = 'time';
            $values[':time'] = (int) $time;
        }

        if ($starttime != null) {
            $fieldNames[] = 'starttime';
            $values[':starttime'] = (int) $starttime;
        }

        $fieldNames[] = 'allday';
        $values[':allday'] = $allday ? 1 : 0;

        $stmt = $this->pdo->prepare("INSERT INTO ".$this->table." (".implode(', ', $fieldNames).") VALUES (".implode(', ', array_keys($values)).")");
        $stmt->execute($values);

        return $this->pdo->lastInsertId();
    }

    public function deleteReminder($eventId, $user = null)
    {
        $userWhere = '';
        $params = array($this->getEventId($eventId));
        if (isset($user)) {
            $userWhere = ' AND user = ?';
            $params[] = $user;
        }
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->table.' WHERE eventid = ?'.$userWhere);
        return $stmt->execute($params);
    }

    public function deleteOutdatedReminders($time)
    {
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->table.' WHERE time < ?');
        return $stmt->execute([$time]);
    }

    public function deleteReminderByCalendar($calendarUri)
    {
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->table.' WHERE calendaruri = ?');
        return $stmt->execute(array($calendarUri));
    }

    public function deleteRemindersByUser($user)
    {
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->table.' WHERE user = ?');
        return $stmt->execute(array($user));
    }

    public static function getEventId($uri)
    {
        return basename($uri, '.ics');
    }

    public static function getEventUri($uri)
    {
        return basename($uri);
    }

    public static function getCalendarUri($uri)
    {
        return dirname($uri);
    }

    public static function isEvent($uri)
    {
        $sUriExt = pathinfo($uri, PATHINFO_EXTENSION);
        return ($sUriExt != null && strtoupper($sUriExt) == 'ICS');
    }

    public static function isCalendar($uri)
    {
        return (strpos($uri, 'calendars/') !== false ||	strpos($uri, 'delegation/') !== false);
    }

    public function updateReminder($uri, $data, $user)
    {
        $oCalendarModule =  \Aurora\System\Api::GetModule('Calendar');
        if ($oCalendarModule instanceof Module && self::isCalendar($uri) && self::isEvent($uri)) {
            $calendarUri = trim($this->getCalendarUri($uri), '/');
            $eventId = $this->getEventId($uri);
            $oUser = \Afterlogic\DAV\Utils::GetUserByPublicId($user);

            $aUsers = [];
            $oCalendar = $oCalendarModule->getManager()->getCalendar($user, basename($calendarUri));
            if ($oCalendar) {
                $aCalendars = \Afterlogic\DAV\Backend::Caldav()->getCalendarInstances($oCalendar->IntId);

                foreach ($aCalendars as $aCalendar) {
                    $aUsers[] = [
                        basename($aCalendar['principaluri']),
                        $aCalendar['uri']
                    ];
                }
            }

            foreach ($aUsers as $aUser) {
                $this->deleteReminder($eventId, $aUser[0]);
            }

            $data = str_replace('VTODO', 'VEVENT', $data);
            $vCal = \Sabre\VObject\Reader::read($data, \Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES);
            $aBaseEvents = [];
            if ($vCal instanceof VCalendar) {
                $aBaseEvents = $vCal->getBaseComponents('VEVENT');
                $bAllDay = false;
                if (isset($aBaseEvents[0])) {
                    $iOffset = 0;
                    $iWorkDayStartsOffset = 0;
                    $oBaseEvent = $aBaseEvents[0];
                    $oNowDT = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

                    $bAllDay = false;
                    if ($oBaseEvent->DTSTART) {
                        $bAllDay = !$oBaseEvent->DTSTART->hasTime();
                        $oStartDT = $oBaseEvent->DTSTART->getDateTime();
                        $oStartDT = $oStartDT->setTimezone(new \DateTimeZone('UTC'));

                        $iReminderTime = false;
                        if ($bAllDay && $oUser instanceof \Aurora\Modules\Core\Models\User) {
                            $oClientTZ = isset($oClientTZ) ? $oClientTZ : new \DateTimeZone($oUser->DefaultTimeZone);
                            $oNowDTClientTZ = isset($oNowDTClientTZ) ? $oNowDTClientTZ : new \DateTime("now", $oClientTZ);
                            $iOffset = $oNowDTClientTZ->getOffset(); //difference between UTC and time zone in allDay Event
                            //send reminder at WorkDayStarts time
                            $iWorkDayStartsOffset = $oUser->getExtendedProp('Calendar::WorkdayStarts') * 3600;
                        }
                        //NextRepeat
                        if (isset($oBaseEvent->RRULE)) {
                            $oEndDT = \Aurora\Modules\Calendar\Classes\Helper::getRRuleIteratorNextRepeat($oNowDT, $oBaseEvent);
                            $aEvents = null;
                            if ($oCalendar && $user && $oEndDT) {
                                $oEndDT = $oEndDT->setTimezone(new \DateTimeZone('UTC'));
                                if ($bAllDay) {
                                    //add 1 day to EndDate in allDayEvent case
                                    $oEndDT = $oEndDT->add(new \DateInterval('P1D'));
                                }
                                $vCalExpanded = $vCal->expand(
                                    \Sabre\VObject\DateTimeParser::parse($oNowDT->format("Ymd\THis\Z")),
                                    \Sabre\VObject\DateTimeParser::parse($oEndDT->format("Ymd\T235959\Z"))
                                );
                                $aEvents = \Aurora\Modules\Calendar\Classes\Parser::parseEvent($user, $oCalendar, $vCalExpanded, $vCal);
                            }

                            if (is_array($aEvents)) {
                                foreach ($aEvents as $key => $value) {  //ignore events with triggered reminders
                                    if (!($value['alarms']) || (($value['startTS'] + $iWorkDayStartsOffset)  - min($value['alarms']) * 60) < $oNowDT->getTimestamp() + $iOffset) {
                                        unset($aEvents[$key]);
                                    }
                                }
                                $aEvent = reset($aEvents);
                                if ($aEvent !== false && is_array($aEvent) && isset($aEvent['alarms']) && isset($aEvent['startTS'])) {
                                    $aAlarms = $aEvent['alarms'];
                                    sort($aAlarms);
                                    //search nearest alarm
                                    $i = 0;
                                    do {
                                        $iReminderTime = $aEvent['startTS'] - $aAlarms[$i] * 60;
                                        $i++;
                                    } while ($i < count($aAlarms) && (($aEvent['startTS'] + $iWorkDayStartsOffset) - $aAlarms[$i] * 60) > $oNowDT->getTimestamp() + $iOffset);
                                    $iReminderTime = $iReminderTime + $iWorkDayStartsOffset;
                                }
                            }
                        }
                        if ($iReminderTime == false) {
                            $oStartDT = \Aurora\Modules\Calendar\Classes\Helper::getNextRepeat($oNowDT, $oBaseEvent, (string) $oBaseEvent->UID);
                            if ($oStartDT) {
                                $iReminderTime = \Aurora\Modules\Calendar\Classes\Helper::getActualReminderTime($oBaseEvent, $oNowDT, $oStartDT, $iWorkDayStartsOffset, $iOffset);
                            }
                        }
                        if ($iReminderTime !== false) {
                            $iStartTS = $oStartDT->getTimestamp();

                            foreach ($aUsers as $aUser) {
                                $this->addReminder($aUser[0], $aUser[1], $eventId, $iReminderTime - $iOffset, $iStartTS - $iOffset, $bAllDay);
                            }
                        }
                    }
                }
            }
        }
    }
}
