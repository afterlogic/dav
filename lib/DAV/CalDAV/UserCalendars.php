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

		$aCalendars = $this->caldavBackend->getCalendarsForUser(
				$this->principalInfo['uri']
		);
		
		$aObjs = array();
        foreach($aCalendars as $aCalendarInfo) {
			
            if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) {
                if (isset($aCalendarInfo['{http://calendarserver.org/ns/}shared-url'])) {
					$aObjs[] = new SharedCalendar(
							$this->caldavBackend,
							$aCalendarInfo, 
							$this->principalInfo
					);
                } else {
                    $aObjs[] = new ShareableCalendar(
							$this->caldavBackend, 
							$aCalendarInfo
					);
                }
            } else {
                $aObjs[] = new Calendar(
						$this->caldavBackend, 
						$aCalendarInfo
				);
            }
        }
        $aObjs[] = new \Sabre\CalDAV\Schedule\Outbox(
				$this->principalInfo['uri']
		);

        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SchedulingSupport) {
            $aObjs[] = new \Sabre\CalDAV\Schedule\Inbox(
					$this->caldavBackend, $this->principalInfo['uri']
			);
            $aObjs[] = new \Sabre\CalDAV\Schedule\Outbox(
					$this->principalInfo['uri']
			);
        }

        // If the backend supports subscriptions, we'll add those as well,
        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SubscriptionSupport) {
            foreach ($this->caldavBackend->getSubscriptionsForUser(
					$this->principalInfo['uri']) as $subscription) {
                $aObjs[] = new \Sabre\CalDAV\Subscriptions\Subscription(
						$this->caldavBackend, 
						$subscription
				);
            }
        }		

		// We're adding a notifications node, if it's supported by the backend.
        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\NotificationSupport && 
				\CApi::GetConf('labs.dav.caldav.notification', false)) {
            $aObjs[] = new \Sabre\CalDAV\Notifications\Collection(
					$this->caldavBackend, 
					$this->principalInfo['uri']
			);
        }
		return $aObjs;

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
            return new \Sabre\CalDAV\Schedule\Inbox(
					$this->caldavBackend, 
					$this->principalInfo['uri']
			);
        }
        if ($name === 'outbox' && $this->caldavBackend instanceof \Sabre\CalDAV\Backend\SchedulingSupport) {
            return new \Sabre\CalDAV\Schedule\Outbox(
					$this->principalInfo['uri']
			);
        }
        if ($name === 'notifications' && $this->caldavBackend instanceof \Sabre\CalDAV\Backend\NotificationSupport) {
            return new \Sabre\CalDAV\Notifications\Collection(
					$this->caldavBackend, 
					$this->principalInfo['uri']
			);
        }

        // Calendars
        foreach ($this->caldavBackend->getCalendarsForUser(
				$this->principalInfo['uri']) as $calendar) {
            if ($calendar['uri'] === $name) {
				
                if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) {
                    if (isset($calendar['{http://calendarserver.org/ns/}shared-url'])) {
                        return new SharedCalendar(
								$this->caldavBackend, 
								$calendar,  
								$this->principalInfo
						);
                    } else {
                        return new ShareableCalendar(
								$this->caldavBackend, 
								$calendar
						);
                    }
                } else {
                    return new Calendar(
							$this->caldavBackend, 
							$calendar
					);
                }
            }
        }

        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SubscriptionSupport) {
            foreach ($this->caldavBackend->getSubscriptionsForUser(
					$this->principalInfo['uri']) as $subscription) {
                if ($subscription['uri'] === $name) {
                    return new \Sabre\CalDAV\Subscriptions\Subscription(
							$this->caldavBackend, 
							$subscription
					);
                }
            }

        }

        throw new \Sabre\DAV\Exception\NotFound('Node with name \'' . $name . '\' could not be found');

    }

}
