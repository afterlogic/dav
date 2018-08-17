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
	
    /**
     * Returns a list of calendars
     *
     * @return array
     */
    public function getChildren() {

		$this->init();
		$aChildren = parent::getChildren();
		
		if ($this->allowSharing())
		{
			$sTenantPrincipal = $this->getTenantPrincipal(basename($this->principalInfo['uri']));
			foreach ( $this->caldavBackend->getCalendarsForUser($sTenantPrincipal) as $calendar) 
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

					$aChildren[] = new SharedWithAllCalendar($this->caldavBackend, $calendar);
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

							$oChild = new SharedWithAllCalendar($this->caldavBackend, $calendar);
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
