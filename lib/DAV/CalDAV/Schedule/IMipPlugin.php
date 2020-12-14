<?php

namespace Afterlogic\DAV\CalDAV\Schedule;

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

        $subject = 'SabreDAV iTIP message';
        switch (strtoupper($iTipMessage->method)) {
            case 'REPLY' :
                $subject = 'Re: ' . $summary;
                break;
            case 'REQUEST' :
                $subject = $summary;
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

        \Aurora\Modules\CalendarMeetingsPlugin\Classes\Helper::sendAppointmentMessage($senderEmail, $recipient, (string) $subject, $iTipMessage->message->serialize(), $iTipMessage->method);
        $iTipMessage->scheduleStatus = '1.1; Scheduling message is sent via iMip';

        return false;
    }
}
