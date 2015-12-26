<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CalDAV;

class UserCalendars extends \Sabre\CalDAV\CalendarHome{

    /**
     * Returns a list of calendars
     *
     * @return array
     */
    public function getChildren() {

		$calendars = $this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']);
		$oUserManager = \CApi::GetCoreManager('users');
		$oAccount = null;
		if ($oUserManager) {
			$oAccount = $oUserManager->getAccountById($this->principalInfo['id']);
		}
        if (count($calendars) === 0) {
			$this->caldavBackend->createCalendar(
				$this->principalInfo['uri'], 
				\Sabre\DAV\UUIDUtil::getUUID(), 
				[
					'{DAV:}displayname' => \CApi::ClientI18N('CALENDAR/CALENDAR_DEFAULT_NAME', $oAccount),
					'{'.\Sabre\CalDAV\Plugin::NS_CALENDARSERVER.'}getctag' => 1,
					'{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}calendar-description' => '',
					'{http://apple.com/ns/ical/}calendar-color' => \Afterlogic\DAV\Constants::CALENDAR_DEFAULT_COLOR,
					'{http://apple.com/ns/ical/}calendar-order' => 0
				]
			);
			$calendars = $this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']);
		}
		
		$objs = array();
        foreach($calendars as $calendar) {
			
            if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) {
                if (isset($calendar['{http://calendarserver.org/ns/}shared-url'])) {
					$objs[] = new SharedCalendar($this->caldavBackend, $calendar, $this->principalInfo);
                } else {
                    $objs[] = new ShareableCalendar($this->caldavBackend, $calendar);
                }
            } else {
                $objs[] = new Calendar($this->caldavBackend, $calendar);
            }
        }
        $objs[] = new \Sabre\CalDAV\Schedule\Outbox($this->principalInfo['uri']);

        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SchedulingSupport) {
            $objs[] = new \Sabre\CalDAV\Schedule\Inbox($this->caldavBackend, $this->principalInfo['uri']);
            $objs[] = new \Sabre\CalDAV\Schedule\Outbox($this->principalInfo['uri']);
        }

        // If the backend supports subscriptions, we'll add those as well,
        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SubscriptionSupport) {
            foreach ($this->caldavBackend->getSubscriptionsForUser($this->principalInfo['uri']) as $subscription) {
                $objs[] = new \Sabre\CalDAV\Subscriptions\Subscription($this->caldavBackend, $subscription);
            }
        }		

		// We're adding a notifications node, if it's supported by the backend.
        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\NotificationSupport && 
				\CApi::GetConf('labs.dav.caldav.notification', false)) {
            $objs[] = new \Sabre\CalDAV\Notifications\Collection($this->caldavBackend, $this->principalInfo['uri']);
        }
		return $objs;

    }
	
    /**
     * Returns a single calendar, by name
     *
     * @param string $name
     * @return Calendar
     */
    function getChild($name) {

        // Special nodes
        if ($name === 'inbox' && $this->caldavBackend instanceof \Sabre\CalDAV\Backend\SchedulingSupport) {
            return new Schedule\Inbox($this->caldavBackend, $this->principalInfo['uri']);
        }
        if ($name === 'outbox' && $this->caldavBackend instanceof \Sabre\CalDAV\Backend\SchedulingSupport) {
            return new Schedule\Outbox($this->principalInfo['uri']);
        }
        if ($name === 'notifications' && $this->caldavBackend instanceof \Sabre\CalDAV\Backend\NotificationSupport) {
            return new Notifications\Collection($this->caldavBackend, $this->principalInfo['uri']);
        }

        // Calendars
        foreach ($this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']) as $calendar) {
            if ($calendar['uri'] === $name) {
                if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) {
                    if (isset($calendar['{http://calendarserver.org/ns/}shared-url'])) {
                        return new SharedCalendar($this->caldavBackend, $calendar,  $this->principalInfo);
                    } else {
                        return new ShareableCalendar($this->caldavBackend, $calendar);
                    }
                } else {
                    return new Calendar($this->caldavBackend, $calendar);
                }
            }
        }

        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SubscriptionSupport) {
            foreach ($this->caldavBackend->getSubscriptionsForUser($this->principalInfo['uri']) as $subscription) {
                if ($subscription['uri'] === $name) {
                    return new \Sabre\CalDAV\Subscriptions\Subscription($this->caldavBackend, $subscription);
                }
            }

        }

        throw new NotFound('Node with name \'' . $name . '\' could not be found');

    }

}
