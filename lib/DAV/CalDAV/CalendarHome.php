<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class CalendarHome extends \Sabre\CalDAV\CalendarHome
{
    public function getName()
    {
        return 'calendars';
    }

    public function __construct(\Sabre\CalDAV\Backend\BackendInterface $caldavBackend, $principalInfo = null)
    {
        parent::__construct($caldavBackend, $principalInfo);
    }

    public function init()
    {
        if (empty($this->principalInfo)) {
            $this->principalInfo = \Afterlogic\DAV\Server::getCurrentPrincipalInfo();
        }
    }

    public function getACL()
    {
        $this->init();
        return parent::getACL();
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

    protected function allowSharing()
    {
        $oCorporateCalendar = \Aurora\System\Api::GetModule('CorporateCalendar');
        return $oCorporateCalendar && $oCorporateCalendar->getConfig('AllowShare');
    }

    protected function initCalendar($calendar)
    {
        $oCalendar = null;

        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) {
            $oCalendar = new Shared\Calendar($this->caldavBackend, $calendar);
        } else {
            $oCalendar = new Calendar($this->caldavBackend, $calendar);
        }

        return $oCalendar;
    }

    /**
     * Returns a single calendar, by name
     *
     * @param string $name
     * @return Calendar
     */
    protected function _getChild($name)
    {
        // Special nodes
        if ($name === 'inbox' && $this->caldavBackend instanceof \Sabre\CalDAV\Backend\SchedulingSupport) {
            return new \Sabre\CalDAV\Schedule\Inbox($this->caldavBackend, $this->principalInfo['uri']);
        }
        if ($name === 'outbox' && $this->caldavBackend instanceof \Sabre\CalDAV\Backend\SchedulingSupport) {
            return new \Sabre\CalDAV\Schedule\Outbox($this->principalInfo['uri']);
        }
        if ($name === 'notifications' && $this->caldavBackend instanceof \Sabre\CalDAV\Backend\NotificationSupport) {
            return new \Sabre\CalDAV\Notifications\Collection($this->caldavBackend, $this->principalInfo['uri']);
        }

        // Calendars
        foreach ($this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']) as $calendar) {
            if ($calendar['uri'] === $name) {
                return $this->initCalendar($calendar);
            }
        }

        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SubscriptionSupport) {
            foreach ($this->caldavBackend->getSubscriptionsForUser($this->principalInfo['uri']) as $subscription) {
                if ($subscription['uri'] === $name) {
                    return new \Sabre\CalDAV\Subscriptions\Subscription($this->caldavBackend, $subscription);
                }
            }
        }

        throw new \Sabre\DAV\Exception\NotFound('Node with name \'' . $name . '\' could not be found');
    }

    protected function _getChildren()
    {
        $aChildren = [];
        $calendars = $this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']);
        foreach ($calendars as $calendar) {
            $aChildren[] = $this->initCalendar($calendar);
        }

        $bCountOwnCalendars = 0;
        foreach ($aChildren as $oCalendar) {
            if ($oCalendar instanceof Calendar || ($oCalendar instanceof Shared\Calendar && $oCalendar->isOwned())) {
                $bCountOwnCalendars++;
            }
        }
        $bHasDefault = ($bCountOwnCalendars > 0);

        if (!$bHasDefault) {
            $aCreateCalendarResult = $this->caldavBackend->createCalendar(
                $this->principalInfo['uri'],
                \Afterlogic\DAV\Constants::CALENDAR_DEFAULT_UUID . '-' . \Sabre\DAV\UUIDUtil::getUUID(),
                [
                    '{DAV:}displayname' => \Aurora\Modules\Calendar\Module::getInstance()->i18n('CALENDAR_DEFAULT_NAME'),
                    '{http://apple.com/ns/ical/}calendar-color' => \Afterlogic\DAV\Constants::CALENDAR_DEFAULT_COLOR
                ]
            );

            if ($this->caldavBackend instanceof \Afterlogic\DAV\CalDAV\Backend\PDO && is_array($aCreateCalendarResult) && isset($aCreateCalendarResult[0])) {
                $calendar = $this->caldavBackend->getParentCalendar((int) $aCreateCalendarResult[0]);
                if ($calendar) {
                    $aChildren[] = $this->initCalendar($calendar, $bHasDefault);
                }
            }
        }

        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SchedulingSupport) {
            $aChildren[] = new \Sabre\CalDAV\Schedule\Inbox($this->caldavBackend, $this->principalInfo['uri']);
            $aChildren[] = new \Sabre\CalDAV\Schedule\Outbox($this->principalInfo['uri']);
        }

        // We're adding a notifications node, if it's supported by the backend.
        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\NotificationSupport) {
            $aChildren[] = new \Sabre\CalDAV\Notifications\Collection($this->caldavBackend, $this->principalInfo['uri']);
        }

        // If the backend supports subscriptions, we'll add those as well,
        if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SubscriptionSupport) {
            foreach ($this->caldavBackend->getSubscriptionsForUser($this->principalInfo['uri']) as $subscription) {
                $aChildren[] = new \Sabre\CalDAV\Subscriptions\Subscription($this->caldavBackend, $subscription);
            }
        }

        return $aChildren;
    }

    protected function getChildrenForTenantPrincipal($sTenantPrincipal)
    {
        $aChildren = [];

        foreach ($this->caldavBackend->getCalendarsForUser($sTenantPrincipal) as $calendar) {
            if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport && $this->caldavBackend instanceof \Afterlogic\DAV\CalDAV\Backend\PDO) {
                $parentCalendar = $this->caldavBackend->getParentCalendar($calendar['id'][0]);

                if ($parentCalendar) {
                    $shareAccess = $calendar['share-access'];
                    $calendar = $parentCalendar;
                    $calendar['share-access'] = $shareAccess;
                    $calendar['principaluri'] = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . \Afterlogic\DAV\Server::getUser();
                }

                $oSharedWithAllCalendar = new SharedWithAll\Calendar($this->caldavBackend, $calendar);

                $bOwner = false;
                foreach ($oSharedWithAllCalendar->getInvites() as $oSharee) {
                    if ($oSharee->principal === $this->principalInfo['uri'] && $oSharee->access === \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER) {
                        $bOwner = true;
                        break;
                    }
                }
                if (!$bOwner) {
                    $aChildren[] = $oSharedWithAllCalendar;
                }
            }
        }

        return $aChildren;
    }

    /**
     * Returns a list of calendars
     *
     * @return array
     */
    public function getChildren()
    {
        $this->init();

        $aChildren = $this->_getChildren();

        if ($this->allowSharing()) {
            $aParrenCalendarsId = array_map(
                function ($oChild) {
                    if ($oChild instanceof \Sabre\CalDAV\Calendar) {
                        $aProps = $oChild->getProperties(['id']);
                        if (isset($aProps['id'])) {
                            return $aProps['id'][0];
                        }
                    }
                },
                $aChildren
            );

            $aChildrenForTenantPrincipal = $this->getChildrenForTenantPrincipal(
                $this->getTenantPrincipal(
                    basename($this->principalInfo['uri'])
                )
            );
            foreach ($aChildrenForTenantPrincipal as $oChild) {
                if ($oChild instanceof \Sabre\CalDAV\Calendar) {
                    $aProps = $oChild->getProperties(['id']);
                    if (isset($aProps['id']) && !in_array($aProps['id'][0], $aParrenCalendarsId)) {
                        $aChildren[] = $oChild;
                    }
                }
            }
        } else {
            foreach ($aChildren as $sKey => $oChild) {
                if ($oChild instanceof \Sabre\CalDAV\SharedCalendar && $oChild->getShareAccess() !== \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER) {
                    unset($aChildren[$sKey]);
                }
            }
        }

        return $aChildren;
    }

    /**
     * Returns a single calendar, by name
     *
     * @param string $name
     * @return Calendar
     */
    public function getChild($name)
    {
        $this->init();
        $oChild = false;
        try {
            $oChild = $this->_getChild($name);
        } catch (\Sabre\DAV\Exception\NotFound $oEx) {
            if ($this->allowSharing()) {
                $oChild = $this->getChildForTenantPrincipal(
                    $name,
                    $this->getTenantPrincipal(basename($this->principalInfo['uri']))
                );
            }
            if (!$oChild) {
                throw $oEx;
            }
        }

        return $oChild;
    }

        /**
     * Returns a single calendar, by name
     *
     * @param string $name
     * @return Calendar
     */
    public function getChildForTenantPrincipal($name, $principal)
    {
        $oChild = false;
        foreach ($this->caldavBackend->getCalendarsForUser($principal) as $calendar) {
            if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport && $this->caldavBackend instanceof \Afterlogic\DAV\CalDAV\Backend\PDO) {
                $parentCalendar = $this->caldavBackend->getParentCalendar($calendar['id'][0]);
                if ($parentCalendar && $parentCalendar['uri'] === $name) {
                    $calendar['id'] = $parentCalendar['id'];
                    $calendar['uri'] = $parentCalendar['uri'];
                    $calendar['principaluri'] = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . \Afterlogic\DAV\Server::getUser();

                    $oChild = new SharedWithAll\Calendar($this->caldavBackend, $calendar);
                    break;
                }
            }
        }

        return $oChild;
    }

    public function getPublicChild($name)
    {
        $oChild = false;
        if ($this->caldavBackend instanceof \Afterlogic\DAV\CalDAV\Backend\PDO) {
            $calendar = $this->caldavBackend->getPublicCalendar($name);
            if ($calendar) {
                if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) {
                    $oChild = new PublicCalendar($this->caldavBackend, $calendar);
                }
            }
        }
        return $oChild;
    }
}
