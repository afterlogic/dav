<?php

namespace Afterlogic\DAV\CalDAV\Schedule;

use Sabre\VObject\ITip\Message;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\ITip;
use Sabre\CalDAV\ICalendarObject;

class Plugin extends \Sabre\CalDAV\Schedule\Plugin
{
    protected $customSignificantChangeProperties = [
        'SUMMARY', 
        'DESCRIPTION', 
        'LOCATION',
        'ATTENDEE'
    ];

/**
     * Event handler for the 'schedule' event.
     *
     * This handler attempts to look at local accounts to deliver the
     * scheduling object.
     */
    public function scheduleLocalDelivery(\Sabre\VObject\ITip\Message $iTipMessage)
    {
        $aclPlugin = $this->server->getPlugin('acl');

        // Local delivery is not available if the ACL plugin is not loaded.
        if (!$aclPlugin) {
            return;
        }

        $caldavNS = '{'.self::NS_CALDAV.'}';

        $principalUri = $aclPlugin->getPrincipalByUri($iTipMessage->recipient);
        if (!$principalUri) {
            $iTipMessage->scheduleStatus = '3.7;Could not find principal.';

            return;
        }

        $caldavPlugin = $this->server->getPlugin('caldav');
        $homePath = $caldavPlugin->getCalendarHomeForPrincipal($principalUri);
        if (!$homePath) {
            $iTipMessage->scheduleStatus = '5.2;Could not locate a calendar-home-set';

            return;
        }
        $inboxPath = $homePath.'/inbox/';

        if ('REPLY' === $iTipMessage->method) {
            $privilege = 'schedule-deliver-reply';
        } else {
            $privilege = 'schedule-deliver-invite';
        }

        // We may not have sufficient privileges to check this, so we are
        // temporarily turning off ACL to let this come through.
        $this->server->removeListener('propFind', [$aclPlugin, 'propFind']);
        $hasPrivilege = $aclPlugin->checkPrivileges($inboxPath, $caldavNS.$privilege, \Sabre\DAVACL\Plugin::R_PARENT, false);
        $this->server->on('propFind', [$aclPlugin, 'propFind'], 20);

        if (!$hasPrivilege) {
            $iTipMessage->scheduleStatus = '3.8;insufficient privileges: '.$privilege.' is required on the recipient schedule inbox.';

            return;
        }

        // We deliberately avoid resolving the recipient's calendars through
        // $this->server->tree / getProperties(): the "calendars" node is a
        // single object shared for the whole request (it's added once when
        // the server boots - see Server::initCalendars()), and it lazily
        // latches onto whichever principal first initializes it
        // (CalendarHome::init() only sets $principalInfo "if empty"). That's
        // normally the sender/organizer, since their own request always
        // touches it first (e.g. the PUT that triggered this scheduling).
        // Going through the shared tree would then silently read/write
        // calendar objects against the organizer's own calendars instead of
        // the recipient's. A CalendarHome instance we construct ourselves
        // resolves everything from its own $principalInfo instead, so it's
        // safe to use for a different principal than the one the tree is
        // currently bound to.
        $recipientPrincipalInfo = ['uri' => $principalUri, 'id' => basename($principalUri)];
        $home = new \Afterlogic\DAV\CalDAV\CalendarHome(\Afterlogic\DAV\Backend::Caldav(), $recipientPrincipalInfo);
        $inbox = $home->getChild('inbox');

        $targetCalendar = null;
        foreach ($home->getChildren() as $child) {
            $isOwnCalendar = ($child instanceof \Afterlogic\DAV\CalDAV\Calendar)
                || ($child instanceof \Afterlogic\DAV\CalDAV\Shared\Calendar && $child->isOwned());
            if ($isOwnCalendar) {
                $targetCalendar = $child;
                break;
            }
        }
        if (!$targetCalendar) {
            $iTipMessage->scheduleStatus = '5.2;Could not find a schedule-default-calendar-URL property';

            return;
        }

        // Next, we're going to find out if the item already exits in one of
        // the users' calendars.
        $uid = $iTipMessage->uid;

        $newFileName = 'sabredav-'.\Sabre\DAV\UUIDUtil::getUUID().'.ics';

        $currentObject = null;
        $objectNode = null;
        $oldICalendarData = null;
        $isNewNode = false;

        $result = $home->getCalendarObjectByUID($uid);
        if ($result) {
            // There was an existing object, we need to update probably.
            // $result is "<calendar uri>/<object uri>", relative to $home.
            list($existingCalendarUri, $existingObjectUri) = explode('/', $result, 2);
            $existingCalendar = ($existingCalendarUri === $targetCalendar->getName())
                ? $targetCalendar
                : $home->getChild($existingCalendarUri);
            $objectNode = $existingCalendar->getChild($existingObjectUri);
            $oldICalendarData = $objectNode->get();
            $currentObject = \Sabre\VObject\Reader::read($oldICalendarData);
        } else {
            $isNewNode = true;
        }

        $broker = new \Sabre\VObject\ITip\Broker();
        $broker->significantChangeProperties = array_merge(
            $broker->significantChangeProperties,
            $this->customSignificantChangeProperties
        );
        $newObject = $broker->processMessage($iTipMessage, $currentObject);

        $inbox->createFile($newFileName, $iTipMessage->message->serialize());

        if (!$newObject) {
            // We received an iTip message referring to a UID that we don't
            // have in any calendars yet, and processMessage did not give us a
            // calendarobject back.
            //
            // The implication is that processMessage did not understand the
            // iTip message.
            $iTipMessage->scheduleStatus = '5.0;iTip message was not processed by the server, likely because we didn\'t understand it.';

            return;
        }

        // Note that we are bypassing ACL on purpose by calling this directly.
        // We may need to look a bit deeper into this later. Supporting ACL
        // here would be nice.
        if ($isNewNode) {
            $targetCalendar->createFile($newFileName, $newObject->serialize());
        } else {
            // If the message was a reply, we may have to inform other
            // attendees of this attendees status. Therefore we're shooting off
            // another itipMessage.
            if ('REPLY' === $iTipMessage->method) {
                $this->processICalendarChange(
                    $oldICalendarData,
                    $newObject,
                    [$iTipMessage->recipient],
                    [$iTipMessage->sender]
                );
            }
            $objectNode->put($newObject->serialize());
        }
        $iTipMessage->scheduleStatus = '1.2;Message delivered locally';
    }
    
    public function scheduleLocalDeliveryParent(\Sabre\VObject\ITip\Message $iTipMessage)
    {
        parent::scheduleLocalDelivery($iTipMessage);
    }
    
    /**
    * This method is triggered before a file gets deleted.
    *
    * We use this event to make sure that when this happens, attendees get
    * cancellations, and organizers get 'DECLINED' statuses.
    *
    * @param string $path
    */
   public function beforeUnbind($path)
   {
       // FIXME: We shouldn't trigger this functionality when we're issuing a
       // MOVE. This is a hack.
       if ('MOVE' === $this->server->httpRequest->getMethod()) {
           return;
       }

       $node = $this->server->tree->getNodeForPath($path);

       if (!$node instanceof ICalendarObject || $node instanceof \Sabre\CalDAV\Schedule\ISchedulingObject) {
           return;
       }

       $reflector = new \ReflectionObject($this);
       $method = $reflector->getMethod('scheduleReply');
       $method->setAccessible(true);
       $scheduleReplyResult = $method->invoke($this, $this->server->httpRequest);

       if (!$scheduleReplyResult) {
           return;
       }

       $addresses = $this->getAddressesForPrincipal(
           $node->getOwner()
       );

       $broker = new ITip\Broker();
       $broker->significantChangeProperties = array_merge(
           $broker->significantChangeProperties, 
           $this->customSignificantChangeProperties
       );
       $messages = $broker->parseEvent(null, $addresses, $node->get());

       foreach ($messages as $message) {
           $this->deliver($message);
       }
   }

    /**
     * This method looks at an old iCalendar object, a new iCalendar object and
     * starts sending scheduling messages based on the changes.
     *
     * A list of addresses needs to be specified, so the system knows who made
     * the update, because the behavior may be different based on if it's an
     * attendee or an organizer.
     *
     * This method may update $newObject to add any status changes.
     *
     * @param VCalendar|string $oldObject
     * @param array            $ignore    any addresses to not send messages to
     * @param bool             $modified  a marker to indicate that the original object
     *                                    modified by this process
     */
    protected function processICalendarChange($oldObject, VCalendar $newObject, array $addresses, array $ignore = [], &$modified = false)
    {
        $broker = new ITip\Broker();
        $broker->significantChangeProperties = array_merge(
            $broker->significantChangeProperties, 
            $this->customSignificantChangeProperties
        );
        $messages = $broker->parseEvent($newObject, $addresses, $oldObject);

        if ($messages) {
            $modified = true;
        }

        foreach ($messages as $message) {
            if (in_array($message->recipient, $ignore)) {
                continue;
            }

            $this->deliver($message);

            if (isset($newObject->VEVENT->ORGANIZER) && ($newObject->VEVENT->ORGANIZER->getNormalizedValue() === $message->recipient)) {
                if ($message->scheduleStatus) {
                    $newObject->VEVENT->ORGANIZER['SCHEDULE-STATUS'] = $message->getScheduleStatus();
                }
                unset($newObject->VEVENT->ORGANIZER['SCHEDULE-FORCE-SEND']);
            } else {
                if (isset($newObject->VEVENT->ATTENDEE)) {
                    foreach ($newObject->VEVENT->ATTENDEE as $attendee) {
                        if ($attendee->getNormalizedValue() === $message->recipient) {
                            if ($message->scheduleStatus) {
                                $attendee['SCHEDULE-STATUS'] = $message->getScheduleStatus();
                            }
                            unset($attendee['SCHEDULE-FORCE-SEND']);
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns a list of addresses that are associated with a principal.
     *
     * @param string $principal
     * @return array
     */
    protected function getAddressesForPrincipal($principal)
    {
        $CUAS = '{' . self::NS_CALDAV . '}calendar-user-address-set';

        $properties = $this->server->getProperties(
            $principal,
            [$CUAS]
        );

        // If we can't find this information, we'll stop processing
        if (!isset($properties[$CUAS])) {
            return;
        }

        $addresses = $properties[$CUAS]->getHrefs();

        $iPos = strpos($principal, 'principals/');
        if ($iPos !== false) {
            $addresses[] = 'mailto:' . \trim(substr($principal, $iPos + 11), '/');
        }
        return $addresses;
    }
}
