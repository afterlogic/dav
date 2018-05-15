<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CalDAV;

class CalendarHome extends \Sabre\CalDAV\CalendarHome{


	/**
	 * @param string $sUserUUID
	 *
	 * @return array
	 */
	protected function getPrincipalInfo($sUserPublicId)
	{
		$aPrincipal = array();

		$aPrincipalProperties = \Afterlogic\DAV\Backend::Principal()->getPrincipalByPath(
			\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sUserUUID
		);
		if (isset($aPrincipalProperties['uri'])) 
		{
			$aPrincipal['uri'] = $aPrincipalProperties['uri'];
			$aPrincipal['id'] = $aPrincipalProperties['id'];
		} 
		else 
		{
			$aPrincipal['uri'] = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sUserUUID;
			$aPrincipal['id'] = -1;
		}
		return $aPrincipal;
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
	
    /**
     * Returns a list of calendars
     *
     * @return array
     */
    public function getChildren() {

		$aOwnChildren = parent::getChildren();
		$sTenantPrincipal = $this->getTenantPrincipal(basename($this->principalInfo['uri']));

		$aChildrenSharedWithAll = [];
		foreach ( $this->caldavBackend->getCalendarsForUser($sTenantPrincipal) as $calendar) 
		{
			if ($this->caldavBackend instanceof \Sabre\CalDAV\Backend\SharingSupport) 
			{
				$parentCalendar = $this->caldavBackend->getParentCalendar($calendar['id'][0]);
				if ($parentCalendar)
				{
					$calendar['id'] = $parentCalendar['id'];
					$calendar['principaluri'] = $parentCalendar['principaluri'];
					$calendar['uri'] = $parentCalendar['uri'];
				}
			
				$aChildrenSharedWithAll[] = new SharedWithAllCalendar($this->caldavBackend, $calendar);
			} 
		}

		return array_merge(
			$aChildrenSharedWithAll,
			$aOwnChildren
		);
    }
	
    /**
     * Returns a single calendar, by name
     *
     * @param string $name
     * @return Calendar
     */
    function getChild($name) {
		
		try
		{
			$oChild = parent::getChild($name);
		}
		catch (\Sabre\DAV\Exception\NotFound $oEx)
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
						$calendar['principaluri'] = $parentCalendar['principaluri'];
						$calendar['uri'] = $parentCalendar['uri'];
					   
						$oChild = new SharedWithAllCalendar($this->caldavBackend, $calendar);
						break;
					}
				} 
			}
		}
		
		return $oChild;
	}

}
