<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV\Shared;

use Sabre\CalDAV\Backend\SharingSupport;

/**
 * This object represents a CalDAV calendar that is shared by a different user.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Calendar extends \Sabre\CalDAV\SharedCalendar
{
    use \Afterlogic\DAV\CalDAV\CalendarTrait;

    protected function updateReminders($principaluri, $calendarid, $currentInvites, array $sharees)
    {
        if ($this->caldavBackend instanceof \Afterlogic\DAV\CalDAV\Backend\PDO) {
            $calendarInstances = $this->caldavBackend->getCalendarInstances($calendarid[0]);
            $userCalendars = [];
            foreach ($calendarInstances as $calendar) {
                $userCalendars[basename($calendar['principaluri'])] = $calendar['uri'];
            }
            $reminders = \Afterlogic\DAV\Backend::Reminders()->getRemindersForCalendar(basename($principaluri), $this->getName());
            if (is_array($reminders) && count($reminders) > 0) {
                foreach ($sharees as $sharee) {
                    if ($sharee->access === \Sabre\DAV\Sharing\Plugin::ACCESS_NOACCESS) {
                        if ($sharee->principal !== $principaluri) {
                            foreach ($reminders as $reminder) {
                                \Afterlogic\DAV\Backend::Reminders()->deleteReminder(
                                    $reminder['eventid'],
                                    basename($sharee->principal)
                                );
                            }
                        }
                        continue;
                    }

                    foreach ($currentInvites as $oldSharee) {
                        if ($oldSharee->href === $sharee->href) {
                            continue 2;
                        }
                    }

                    foreach ($reminders as $reminder) {
                        $userPrincipal = basename($sharee->principal);
                        if (isset($userCalendars[$userPrincipal])) {
                            \Afterlogic\DAV\Backend::Reminders()->addReminder(
                                $userPrincipal,
                                $userCalendars[$userPrincipal],
                                $reminder['eventid'],
                                $reminder['time'],
                                $reminder['starttime'],
                                $reminder['allday']
                            );
                        }
                    }
                }
            }
        }
    }

    public function isOwned()
    {
        return $this->getShareAccess() === \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER;
    }

    /**
     * Updates the list of sharees.
     *
     * Every item must be a Sharee object.
     *
     * @param \Sabre\DAV\Xml\Element\Sharee[] $sharees
     * @return void
     */
    public function updateInvites(array $sharees)
    {
        $currentInvites = [];
        $props = $this->getProperties(['id', 'principaluri']);
        if ($this->caldavBackend instanceof SharingSupport) {
            $currentInvites = $this->caldavBackend->getInvites($props['id']);
            parent::updateInvites($sharees);
        }

        $this->updateReminders($props['principaluri'], $props['id'], $currentInvites, $sharees);
    }
}
