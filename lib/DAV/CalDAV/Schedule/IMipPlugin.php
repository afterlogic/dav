<?php

namespace Afterlogic\DAV\CalDAV\Schedule;

use Sabre\DAV;
use Sabre\VObject\ITip;
use Aurora\Modules\CalendarMeetingsPlugin\Classes\Helper;
use Aurora\Modules\Core\Module;
use Aurora\System\Api;

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
class IMipPlugin extends \Sabre\CalDAV\Schedule\IMipPlugin
{
    /** @var DAV\Server $server */
    protected $server;

    public function __construct()
    {
        
    }

    /*
     * This initializes the plugin.
     *
     * This function is called by Sabre\DAV\Server, after
     * addPlugin is called.
     *
     * This method should set up the required event subscriptions.
     *
     * @param DAV\Server $server
     * @return void
     */
    public function initialize(DAV\Server $server)
    {
        parent::initialize($server);
        $this->server = $server;
    }

    /**
     * Event handler for the 'schedule' event.
     *
     * @param ITip\Message $iTipMessage
     * @return void
     */
    public function schedule(ITip\Message $iTipMessage)
    {
        // Check if the event has already passed (fix B: prevent sending invites for past events)
        if ($this->isEventInPast($iTipMessage)) {
            $iTipMessage->scheduleStatus = '5.3;Event is in the past; iTip delivery suppressed';
            return;
        }

        // Not sending any emails if the system considers the update
        // insignificant.
        if (!$iTipMessage->significantChange) {
            if (!$iTipMessage->scheduleStatus) {
                $iTipMessage->scheduleStatus = '1.0;We got the message, but it\'s not significant enough to warrant an email';
            }
            return;
        }

        $summary = $iTipMessage->message->VEVENT->SUMMARY;

        if (parse_url($iTipMessage->sender, PHP_URL_SCHEME) === 'mailto') {
            $senderEmail = substr($iTipMessage->sender, 7);
        } else {
            $iPos = strpos($iTipMessage->sender, 'principals/');
            if ($iPos !== false) {
                $senderEmail = \trim(substr($iTipMessage->sender, $iPos + 11), '/');
            } else {
                return;
            }
        }

        if (parse_url($iTipMessage->recipient, PHP_URL_SCHEME) === 'mailto') {
            $recipient = substr($iTipMessage->recipient, 7);
        } else {
            $iPos = strpos($iTipMessage->recipient, 'principals/');
            if ($iPos !== false) {
                $recipient = \trim(substr($iTipMessage->recipient, $iPos + 11), '/');
            } else {
                return;
            }
        }

        if ($iTipMessage->senderName) {
            $sender = $iTipMessage->senderName . ' <' . $senderEmail . '>';
        } else {
            $sender = $senderEmail;
        }
        if ($iTipMessage->recipientName) {
            $recipient = $iTipMessage->recipientName . ' <' . $recipient . '>';
        }

        $subject = 'iTIP message';
        switch (strtoupper($iTipMessage->method)) {
            case 'REPLY':
                $sPartstat = $iTipMessage->message->VEVENT->ATTENDEE['PARTSTAT']->getValue();
                $oModule = Api::GetModule('CalendarMeetingsPlugin');
                $subject = 'Re: ' . $summary;
                if ($oModule) {
                    switch ($sPartstat) {
                        case 'ACCEPTED':
                            $subject = $oModule->i18N('SUBJECT_PREFFIX_ACCEPTED') . ': '. $summary;
                            break;
                        case 'DECLINED':
                            $subject = $oModule->i18N('SUBJECT_PREFFIX_DECLINED') . ': '. $summary;
                            break;
                        case 'TENTATIVE':
                            $subject = $oModule->i18N('SUBJECT_PREFFIX_TENTATIVE') . ': '. $summary;
                            break;
                    }
                }
                break;
            case 'REQUEST':
                $subject = $summary;
                break;
            case 'CANCEL':
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

        $htmlBody = '';

        if (strtoupper($iTipMessage->method) === 'REQUEST') {
            
            $oUser = Module::getInstance()->GetUserByPublicId($senderEmail);

            /** @var \Sabre\VObject\Property\ICalendar\DateTime $oDTSTART */
            $oDTSTART = $iTipMessage->message->VEVENT->DTSTART;
            $sStartDateFormat = $oDTSTART->hasTime() ? 'D, F d, o, H:i' : 'D, F d, o';
            $sStartDate = \Aurora\Modules\Calendar\Classes\Helper::getStrDate($oDTSTART, $oUser->DefaultTimeZone, $sStartDateFormat);

            $calindarId = '';

            $url = \Afterlogic\Dav\Server::getInstance()->httpRequest->getUrl();
            if (!empty($url)) {
                list($calenndarPath, $eventId) = \Sabre\Uri\split($url);
                $calindarId = basename($calenndarPath);
            }

            $htmlBody = Helper::createHtmlFromEvent(
                $calindarId, 
                $iTipMessage->uid,
                $senderEmail, 
                $recipient, 
                $sStartDate, 
                $iTipMessage->message->VEVENT->LOCATION, 
                $iTipMessage->message->VEVENT->DESCRIPTION
            );

            foreach ($iTipMessage->message->VEVENT->ATTENDEE as &$attendee) {
                $sAttendee = (string) $attendee;
                $iPos = strpos($sAttendee, 'principals/');
                if ($iPos !== false) {
                    $attendee->setValue(trim(substr($sAttendee, $iPos + 11),'/'));
                }
            }

            // Wrap the message sending in a try-catch block (fix A: handle SMTP errors gracefully)
            try {
                Helper::sendAppointmentMessage(
                    $senderEmail,
                    $recipient,
                    $subject,
                    $iTipMessage->message,
                    $iTipMessage->method,
                    $htmlBody
                );
                // Only set success status if sending succeeded
                $iTipMessage->scheduleStatus = '1.1; Scheduling message is sent via iMip';
            } catch (\Exception $e) {
                // Set failure status — this prevents retries for this attendee
                $iTipMessage->scheduleStatus = '5.1; Delivery failed; Error: ' . $e->getMessage();
                // Continue processing other attendees (the foreach loop in parent class will continue)
            }
        }
    }

    /**
     * Checks if the event has already passed (DTEND or DTSTART is in the past)
     * and doesn't extend into the future via RRULE.
     *
     * @param ITip\Message $iTipMessage
     * @return bool
     */
    private function isEventInPast(ITip\Message $iTipMessage): bool
    {
        $vEvent = $iTipMessage->message->VEVENT;

        $utc = new \DateTimeZone('UTC');
        $now = new \DateTime('now', $utc);

        // Check DTSTART
        if (isset($vEvent->DTSTART)) {
            $dtStart = $vEvent->DTSTART->getDateTime();
            $dtStart->setTimezone($utc);

            if ($dtStart < $now) {
                // Check if there's an RRULE that extends into the future
                if (isset($vEvent->RRULE)) {
                    try {
                        $rruleIterator = new \Sabre\VObject\Recur\RRuleIterator(
                            $vEvent->RRULE->getValue(),
                            $dtStart
                        );

                        $rruleIterator->fastForward($now);

                        if ($rruleIterator->valid()) {
                            return false;
                        }

                        return true;
                    } catch (\Exception $e) {
                        return false;
                    }
                }

                return true;
            }
        }

        // Check DTEND if present
        if (isset($vEvent->DTEND)) {
            $dtEnd = $vEvent->DTEND->getDateTime();
            $dtEnd->setTimezone($utc);

            if ($dtEnd < $now) {
                return true;
            }
        }

        return false;
    }

}
