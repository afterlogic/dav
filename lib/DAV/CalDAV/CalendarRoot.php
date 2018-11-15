<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CalDAV;

class CalendarRoot  extends \Sabre\CalDAV\CalendarHome{

	public function getName() {
		
		return 'calendars';
		
	}
	
	public function __construct(\Sabre\CalDAV\Backend\BackendInterface $caldavBackend, $principalInfo = null) {
		
		parent::__construct($caldavBackend, $principalInfo);
	}
	
	public function init() {
		
		if (empty($this->principalInfo))
		{
			$sUserPublicId = \Afterlogic\DAV\Server::getUser();
			if (!empty($sUserPublicId))
			{
				$this->principalInfo = \Afterlogic\DAV\Server::getPrincipalInfo($sUserPublicId);
			}
			
		}
	}
	
	public function getACL() {
		
		$this->init();
		return parent::getACL();
	}


	protected function getTenantPrincipal($sUserPublicId)
	{
		$sTenantPrincipal = 'default_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
		if ($oUser)
		{
			$sTenantPrincipal = $oUser->IdTenant . '_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
		}
		
		return 'principals/' . $sTenantPrincipal;
	}
	
	protected function allowSharing()
	{
		$oCorporateCalendar = \Aurora\System\Api::GetModule('CorporateCalendar');
		return $oCorporateCalendar && $oCorporateCalendar->getConfig('AllowShare');
	}

	protected function _getChildren()
	{
		$aChildren = [];
		$calendars = $this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']);
        foreach ($calendars as $calendar) {
            if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) {
                $aChildren[] = new Shared\Calendar($this->caldavBackend, $calendar);
            } else {
                $aChildren[] = new Calendar($this->caldavBackend, $calendar);
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

		foreach ($this->caldavBackend->getCalendarsForUser($sTenantPrincipal) as $calendar) 
		{
			if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) 
			{
				$parentCalendar = $this->caldavBackend->getParentCalendar($calendar['id'][0]);
				
				if ($parentCalendar)
				{
					$calendar['id'] = $parentCalendar['id'];
					$calendar['uri'] = $parentCalendar['uri'];

					$oUser = \Aurora\System\Api::getAuthenticatedUser();
					if ($oUser)
					{
						$calendar['principaluri'] = 'principals/' . $oUser->PublicId;
					}
				}

				$oSharedWithAllCalendar = new SharedWithAll\Calendar($this->caldavBackend, $calendar);
				
				$bOwner = false;
				foreach ($oSharedWithAllCalendar->getInvites() as $oSharee)
				{
					if ($oSharee->principal === $this->principalInfo['uri'] && $oSharee->access === \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER)
					{
						$bOwner = true;
						break;
					}
				}
				if (!$bOwner)
				{
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
    public function getChildren() {
		
		$this->init();
		
		$aChildren = $this->_getChildren();
		
		if ($this->allowSharing())
		{
			$aParrenCalendarsId = array_map(
				function ($oChild) { 
					if ($oChild instanceof \Sabre\CalDAV\Calendar)
					{
						$aProps = $oChild->getProperties(['id']);
						if (isset($aProps['id']))
						{
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
			foreach ($aChildrenForTenantPrincipal as $oChild)
			{
				if ($oChild instanceof \Sabre\CalDAV\Calendar)
				{
					$aProps = $oChild->getProperties(['id']);
					if (isset($aProps['id']) && !in_array($aProps['id'][0], $aParrenCalendarsId))
					{
						$aChildren[] = $oChild;
					}
				}
			}
		}
		else
		{
			foreach ($aChildren as $sKey => $oChild)
			{
				if ($oChild instanceof \Sabre\CalDAV\SharedCalendar && $oChild->getShareAccess() !== \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER)
				{
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
    function getChild($name) {
		
		$this->init();
		$oChild = false;
		try
		{
			$oChild = parent::getChild($name);
		}
		catch (\Sabre\DAV\Exception\NotFound $oEx)
		{
			if ($this->allowSharing())
			{
				$sTenantPrincipal = $this->getTenantPrincipal(basename($this->principalInfo['uri']));

				foreach ( $this->caldavBackend->getCalendarsForUser($sTenantPrincipal) as $calendar) 
				{
					if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) 
					{
						$parentCalendar = $this->caldavBackend->getParentCalendar($calendar['id'][0]);
						if ($parentCalendar && $parentCalendar['uri'] === $name)
						{
							$calendar['id'] = $parentCalendar['id'];
							$calendar['uri'] = $parentCalendar['uri'];
							
							$oUser = \Aurora\System\Api::getAuthenticatedUser();
							if ($oUser)
							{
								$calendar['principaluri'] = 'principals/' . $oUser->PublicId;
							}

							$oChild = new SharedWithAll\Calendar($this->caldavBackend, $calendar);
							break;
						}
					} 
				}
			}
			if (!$oChild)
			{
				throw $oEx;
			}
		}
		
		return $oChild;
	}
	
	public function getPublicChild($name)
	{
		$oChild = false;
		$calendar = $this->caldavBackend->getPublicCalendar($name);
		if ($calendar)
		{
			if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) 
			{
				$oChild = new PublicCalendar($this->caldavBackend, $calendar);
			} 
		}
		
		return $oChild;
	}

}
