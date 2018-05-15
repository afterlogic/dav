<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CalDAV\Backend;

use Afterlogic\DAV\Constants;

class PDO extends \Sabre\CalDAV\Backend\PDO implements \Sabre\CalDAV\Backend\SharingSupport
{
	/**
	 * @var string
	 */
	protected $dBPrefix;

	/**
	 * Creates the backend
	 */
	public function __construct() 
	{
		parent::__construct(\Aurora\System\Api::GetPDO());
		
		$this->dBPrefix = \Aurora\System\Api::GetSettings()->GetConf('DBPrefix');
		
		$this->calendarTableName = $this->dBPrefix.Constants::T_CALENDARS;
		$this->calendarChangesTableName = $this->dBPrefix.Constants::T_CALENDARCHANGES;
		$this->calendarObjectTableName = $this->dBPrefix . Constants::T_CALENDAROBJECTS;
		$this->schedulingObjectTableName = $this->dBPrefix . Constants::T_SCHEDULINGOBJECTS;
		$this->calendarSubscriptionsTableName = $this->dBPrefix . Constants::T_CALENDARSUBSCRIPTIONS;
		$this->calendarInstancesTableName = $this->dBPrefix . Constants::T_CALENDARINSTANCES;

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
	
    public function getParentCalendar($calendarId) {

        $fields = array_values($this->propertyMap);
        $fields[] = 'calendarid';
        $fields[] = 'uri';
        $fields[] = 'synctoken';
        $fields[] = 'components';
        $fields[] = 'principaluri';
        $fields[] = 'transparent';
        $fields[] = 'access';

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(<<<SQL
SELECT {$this->calendarInstancesTableName}.id as id, $fields FROM {$this->calendarInstancesTableName}
    LEFT JOIN {$this->calendarTableName} ON
        {$this->calendarInstancesTableName}.calendarid = {$this->calendarTableName}.id
WHERE access = 1 AND {$this->calendarInstancesTableName}.calendarid = ? ORDER BY calendarorder ASC
SQL
        );

		$stmt->execute([$calendarId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

		$components = [];
		if ($row['components']) {
			$components = explode(',', $row['components']);
		}

		$calendar = [
			'id'                                                                 => [(int)$row['calendarid'], (int)$row['id']],
			'uri'                                                                => $row['uri'],
			'principaluri'                                                       => $row['principaluri'],
			'{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag'                  => 'http://sabre.io/ns/sync/' . ($row['synctoken'] ? $row['synctoken'] : '0'),
			'{http://sabredav.org/ns}sync-token'                                 => $row['synctoken'] ? $row['synctoken'] : '0',
			'{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet($components),
			'{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp'         => new \Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp($row['transparent'] ? 'transparent' : 'opaque'),
			'share-resource-uri'                                                 => '/ns/share/' . $row['calendarid'],
		];

		$calendar['share-access'] = (int)$row['access'];
		// 1 = owner, 2 = readonly, 3 = readwrite
		if ($row['access'] > 1) {
			// We need to find more information about the original owner.
			//$stmt2 = $this->pdo->prepare('SELECT principaluri FROM ' . $this->calendarInstancesTableName . ' WHERE access = 1 AND id = ?');
			//$stmt2->execute([$row['id']]);

			// read-only is for backwards compatbility. Might go away in
			// the future.
			$calendar['read-only'] = (int)$row['access'] === \Sabre\DAV\Sharing\Plugin::ACCESS_READ;
		}

		foreach ($this->propertyMap as $xmlName => $dbName) {
			$calendar[$xmlName] = $row[$dbName];
		}


        return $calendar;
    }

	public function getCalendarsForUser($principalUri)	
	{
		$calendars = parent::getCalendarsForUser($principalUri);
		
		$sharedWithAll = parent::getCalendarsForUser($this->getTenantPrincipal(basename($principalUri)));
		
		$calendars = array_merge(
			$calendars,
			$sharedWithAll
		);
		
//		print_r($calendars);
		
		return $calendars;
	}

	/**
	 * This method is called when a user replied to a request to share.
	 *
	 * If the user chose to accept the share, this method should return the
	 * newly created calendar url.
	 *
	 * @param string href The sharee who is replying (often a mailto: address)
	 * @param int status One of the SharingPlugin::STATUS_* constants
	 * @param string $calendarUri The url to the calendar thats being shared
	 * @param string $inReplyTo The unique id this message is a response to
	 * @param string $summary A description of the reply
	 * @return null|string
	 */
	public function shareReply($href, $status, $calendarUri, $inReplyTo, $summary = null) {}
	
	/**
	 * Marks this calendar as published.
	 *
	 * Publishing a calendar should automatically create a read-only, public,
	 * subscribable calendar.
	 *
	 * @param bool $value
	 * @return void
	 */
	public function setPublishStatus($calendarId, $value) {}
	
	/**
	 * Returns a list of notifications for a given principal url.
	 *
	 * The returned array should only consist of implementations of
	 * \Sabre\CalDAV\Notifications\INotificationType.
	 *
	 * @param string $principalUri
	 * @return array
	 */
	public function getNotificationsForPrincipal($principalUri)
	{ 
		$aNotifications = array();
/*            
		// get ALL notifications for the user NB. Any read or out of date notifications should be already deleted.
		$stmt = $this->pdo->prepare("SELECT * FROM ".$this->notificationsTableName." WHERE principaluri = ? ORDER BY dtstamp ASC");
		$stmt->execute(array($principalUri));

		while($aRow = $stmt->fetch(\PDO::FETCH_ASSOC)) 
		{
			// we need to return the correct type of notification
			switch($aRow['notification']) 
			{
				case 'Invite':
					$aValues = array();
					// sort out the required data
					if($aRow['id']) 
					{
						$aValues['id'] = $aRow['id'];
					}
					if($aRow['etag']) 
					{
						$aValues['etag'] = $aRow['etag'];
					}
					if($aRow['principaluri']) 
					{
						$aValues['href'] = $aRow['principaluri'];
					}
					if($aRow['dtstamp']) 
					{
						$aValues['dtstamp'] = $aRow['dtstamp'];
					}
					if($aRow['type']) 
					{
						$aValues['type'] = $aRow['type'];
					}
					if($aRow['readonly']) 
					{
						$aValues['readOnly'] = $aRow['readonly'];
					}
					if($aRow['hosturl']) 
					{
						$aValues['hosturl'] = $aRow['hosturl'];
					}
					if($aRow['organizer']) 
					{
						$aValues['organizer'] = $aRow['organizer'];
					}
					if($aRow['commonname']) 
					{
						$aValues['commonName'] = $aRow['commonname'];
					}
					if($aRow['firstname']) 
					{
						$aValues['firstname'] = $aRow['firstname'];
					}
					if($aRow['lastname']) 
					{
						$aValues['lastname'] = $aRow['lastname'];
					}
					if($aRow['summary']) 
					{
						$aValues['summary'] = $aRow['summary'];
					}

					$aNotifications[] = new \Sabre\CalDAV\Notifications\Notification\Invite($aValues);
					break;

				case 'InviteReply':
					break;
				case 'SystemStatus':
					break;
			}

		}
*/
		return $aNotifications;
	}
	
	/**
	 * This deletes a specific notifcation.
	 *
	 * This may be called by a client once it deems a notification handled.
	 *
	 * @param string $sPrincipalUri
	 * @param \Sabre\CalDAV\Notifications\INotificationType $oNotification
	 * @return void
	 */
	public function deleteNotification($sPrincipalUri, \Sabre\CalDAV\Xml\Notification\NotificationInterface $oNotification){ }

}
