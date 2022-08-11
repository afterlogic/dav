<?php

namespace Afterlogic\DAV\CalDAV\Schedule;

use Afterlogic\DAV\Server;
use Aurora\System\Api;
use Sabre\DAV;
use Sabre\VObject\ITip;

/**
 * iMIP handler.
 *
 * This class is responsible for sending out iMIP messages. iMIP is the
 * email-based transport for iTIP. iTIP deals with scheduling operations for
 * iCalendar objects.
 *
 * If you want to customize the email that gets sent out, you can do so by
 * extending this class and overriding the sendMessage method.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class IMipPlugin extends \Sabre\CalDAV\Schedule\IMipPlugin {

    function __construct() {}

    /**
     * Event handler for the 'schedule' event.
     *
     * @param ITip\Message $iTipMessage
     * @return void
     */
    function schedule(ITip\Message $iTipMessage) {

        // Not sending any emails if the system considers the update
        // insignificant.
        if (!$iTipMessage->significantChange) {
            if (!$iTipMessage->scheduleStatus) {
                $iTipMessage->scheduleStatus = '1.0;We got the message, but it\'s not significant enough to warrant an email';
            }
            return;
        }

        $summary = $iTipMessage->message->VEVENT->SUMMARY;

        if (parse_url($iTipMessage->sender, PHP_URL_SCHEME) === 'mailto')
        {
            $senderEmail = substr($iTipMessage->sender, 7);
        }
        else
        {
            $iPos = strpos($iTipMessage->sender, 'principals/');
            if ($iPos !== false)
            {
                $senderEmail = \trim(substr($iTipMessage->sender, $iPos + 11), '/');
            }
            else
            {
                return;
            }
        }

        if (parse_url($iTipMessage->recipient, PHP_URL_SCHEME) === 'mailto')
        {
            $recipient = substr($iTipMessage->recipient, 7);
        }
        else
        {
            $iPos = strpos($iTipMessage->recipient, 'principals/');
            if ($iPos !== false)
            {
                $recipient = \trim(substr($iTipMessage->recipient, $iPos + 11), '/');
            }
            else
            {
                return;
            }
        }

        if ($iTipMessage->senderName) {
            $sender = $iTipMessage->senderName . ' <' . $senderEmail . '>';
        }
        else {
            $sender = $senderEmail;
        }
        if ($iTipMessage->recipientName) {
            $recipient = $iTipMessage->recipientName . ' <' . $recipient . '>';
        }

        $messageBody = $iTipMessage->message->serialize();
        $subject = 'SabreDAV iTIP message';
        switch (strtoupper($iTipMessage->method)) {
            case 'REPLY' :
                $subject = 'Re: ' . $summary;
                break;
            case 'REQUEST' :
                $subject = $summary;
                $server = Server::getInstance();
                $aclPlugin = $server->getPlugin('acl');

                // Local delivery is not available if the ACL plugin is not loaded.
                if (!$aclPlugin) {
                    return;
                }
                $caldavNS = '{'.\Sabre\CalDAV\Schedule\Plugin::NS_CALDAV.'}';
                $principalUri = $aclPlugin->getPrincipalByUri($iTipMessage->sender);
                if (!$principalUri) {
                    $iTipMessage->scheduleStatus = '3.7;Could not find principal.';
        
                    return;
                }
                
                $server->removeListener('propFind', [$aclPlugin, 'propFind']);
                $result = $server->getProperties(
                    $principalUri,
                    [
                        '{DAV:}principal-URL',
                         $caldavNS.'calendar-home-set',
                         $caldavNS.'schedule-default-calendar-URL',
                    ]
                );
                $server->on('propFind', [$aclPlugin, 'propFind'], 20);

                if (!isset($result[$caldavNS.'calendar-home-set'])) {
                    $iTipMessage->scheduleStatus = '5.2;Could not locate a calendar-home-set';
        
                    return;
                }
                if (!isset($result[$caldavNS.'schedule-default-calendar-URL'])) {
                    $iTipMessage->scheduleStatus = '5.2;Could not find a schedule-default-calendar-URL property';
        
                    return;
                }
                $homePath = $result[$caldavNS.'calendar-home-set']->getHref();
                $calendarPath = $result[$caldavNS.'schedule-default-calendar-URL']->getHref();
                $home = $server->tree->getNodeForPath($homePath);
                $calendar = $server->tree->getNodeForPath($calendarPath);
                $cal_props = $calendar->getProperties(['{DAV:}displayname']);
                $sCalendarDisplayName = $cal_props['{DAV:}displayname'];

                $oUser = Api::getAuthenticatedUser();
                $sStartDateFormat = $iTipMessage->message->VEVENT->DTSTART->hasTime() ? 'D, F d, o, H:i' : 'D, F d, o';
                $sStartDate = \Aurora\Modules\Calendar\Classes\Helper::getStrDate(
                    $iTipMessage->message->VEVENT->DTSTART, 
                    $oUser->DefaultTimeZone, 
                    $sStartDateFormat
                );

                $messageBody =  \Aurora\Modules\CalendarMeetingsPlugin\Classes\Helper::createHtmlFromEvent(
                    $calendar->getName(), 
                    $iTipMessage->uid, 
                    $oUser->PublicId, 
                    $recipient, 
                    $sCalendarDisplayName, 
                    $sStartDate, 
                    $iTipMessage->message->VEVENT->LOCATION, 
                    $iTipMessage->message->VEVENT->DESCRIPTION
                );
                break;
            case 'CANCEL' :
                $subject = 'Cancelled: ' . $summary;
                break;
        }

        $headers = [
            'Reply-To: ' . $sender,
            'From: ' . $sender,
            'Content-Type: text/calendar; charset=UTF-8; method=' . $iTipMessage->method,
        ];
        if (DAV\Server::$exposeVersion) {
            $headers[] = 'X-Sabre-Version: ' . DAV\Version::VERSION;
        }

        \Aurora\Modules\CalendarMeetingsPlugin\Classes\Helper::sendAppointmentMessage($senderEmail, $recipient, (string) $subject, $iTipMessage->message->serialize(), $iTipMessage->method, $messageBody);
        $iTipMessage->scheduleStatus = '1.1; Scheduling message is sent via iMip';

        return false;
    }
}
