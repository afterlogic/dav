<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CalDAV\Backend;

use Afterlogic\DAV\Constants;

class PDO extends \Sabre\CalDAV\Backend\PDO implements \Sabre\CalDAV\Backend\SharingSupport
{
	/**
	 * The table name that will be used for calendar shares
	 *
	 * @var string
	 */
	protected $calendarSharesTableName;

	/**
	 * @var string
	 */
	protected $dBPrefix;
	
	/**
	 * List of properties for the calendar shares table
	 * This list maps exactly to the field names in the db table
	 */
	public $sharesProperties = array(
		'calendarid',
		'status',
		'readonly',
		'summary',
		'displayname',
		'color',
		'principaluri'
	);
	
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
		$this->calendarSharesTableName = $this->dBPrefix . Constants::T_CALENDARSHARES;
		$this->schedulingObjectTableName = $this->dBPrefix . Constants::T_SCHEDULINGOBJECTS;
		$this->calendarSubscriptionsTableName = $this->dBPrefix . Constants::T_CALENDARSUBSCRIPTIONS;

	}

    /**
     * Delete a calendar and all it's objects 
     * 
     * @param string $calendarId 
     * @return void
     */
    public function deleteCalendar($calendarId) {

		parent::deleteCalendar($calendarId);
		
		$this->deleteCalendarShares($calendarId);
    }	
	
    public function deleteCalendarShares($calendarId) {

		$stmt = $this->pdo->prepare('DELETE FROM '.$this->calendarSharesTableName.' WHERE calendarid = ?');
		$stmt->execute(array($calendarId));
 
    }	
	
	/**
	 * Updates the list of shares.
	 *
	 * The first array is a list of people that are to be added to the
	 * calendar.
	 *
	 * Every element in the add array has the following properties:
	 *   * href - A url. Usually a mailto: address
	 *   * commonname - Usually a first and last name, or false
	 *   * summary - A description of the share, can also be false
	 *   * readOnly - A boolean value
	 *
	 * Every element in the remove array is just the address string.
	 *
	 * Note that if the calendar is currently marked as 'not shared' by and
	 * this method is called, the calendar should be 'upgraded' to a shared
	 * calendar.
	 *
	 * @param mixed $mCalendarId
	 * @param array $aAdd
	 * @param array $aRemove
	 * @return void
	 */
	public function updateShares($mCalendarId, array $aAdd, array $aRemove = array())
	{ 
		$bAddResult = true;
		$bRemoveResult = true;
		
		if(count($aAdd) > 0) {
			
			foreach ($aAdd as $aAddItem) {
				
				$aFieldNames = array();
				$aFields = array();

				$aFieldNames[] = 'calendarid';
				$aFields[':calendarid'] = $mCalendarId; 

				$aFieldNames[] = 'principaluri';
				$sPrincipalUri = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . str_replace('mailto:', '', $aAddItem['href']);
				$aFields[':principaluri'] = $sPrincipalUri;

				$aFieldNames[] = 'status';
				$aFields[':status'] = \Sabre\CalDAV\SharingPlugin::STATUS_NORESPONSE;

				// check we have all the required fields
				foreach($this->sharesProperties as $sField) {
					
					if(isset($aAddItem[$sField])) {
						
						$aFieldNames[] = $sField;
						$aFields[':'.$sField] = $aAddItem[$sField];
					}
				} 

				$stmt = $this->pdo->prepare("SELECT calendarid FROM ".$this->calendarSharesTableName." WHERE calendarid = ? and principaluri = ?");
				$stmt->execute(array($mCalendarId, $sPrincipalUri));

				if (count($stmt->fetchAll()) === 0) {
					$stmt = $this->pdo->prepare("INSERT INTO ".$this->calendarSharesTableName." (".implode(', ', $aFieldNames).") VALUES (".implode(', ',array_keys($aFields)).")");
					$bAddResult = $stmt->execute($aFields);
				} else {
					$aUpdateFields = array();
					foreach ($aFieldNames as $sFieldName) {
						$aUpdateFields[] = $sFieldName . '= :'. $sFieldName;
					}
					$stmt = $this->pdo->prepare("UPDATE ".$this->calendarSharesTableName." SET ".implode(', ', $aUpdateFields)." WHERE calendarid = :calendarid and principaluri = :principaluri");
					$bAddResult = $stmt->execute($aFields);
				}

				if (isset($aAddItem['displayname'], $aAddItem['summary'])) {
					$stmt = $this->pdo->prepare("UPDATE " . $this->calendarTableName . " SET displayname = ?, description = ? WHERE id = ?");
					$newValues['displayname'] = $aAddItem['displayname'];
					$newValues['description'] = $aAddItem['summary'];
					$newValues['id'] = $mCalendarId;
					$stmt->execute(array_values($newValues));
				}
			}
		}

// 		are we removing any shares?
		if(count($aRemove) > 0) {
			
			$aParams = array($mCalendarId);
			$aMembers = array();
			foreach($aRemove as $sRemoveItem) {
				
				// get the principalid
				$sPrincipalUri = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $sRemoveItem;
				$aMembers[] = $sPrincipalUri;
			}	
			$aParams[] = implode(',', $aMembers);
			$stmt = $this->pdo->prepare("DELETE FROM ".$this->calendarSharesTableName." WHERE calendarid = ? and principaluri IN (?)");
			$bRemoveResult = $stmt->execute($aParams);
		}

//		$stmt = $this->pdo->prepare("UPDATE " . $this->calendarTableName . " SET ctag = ctag + 1 WHERE id = ?");
//        $stmt->execute(array($mCalendarId));		
		
		return $bAddResult && $bRemoveResult;
	}
	
	/**
	 * Returns the list of people whom this calendar is shared with.
	 *
	 * Every element in this array should have the following properties:
	 *   * href - Often a mailto: address
	 *   * commonname - Optional, for example a first + last name
	 *   * status - See the Sabre\CalDAV\SharingPlugin::STATUS_ constants.
	 *   * readOnly - boolean
	 *   * summary - Optional, a description for the share
	 *
	 * @return array
	 */
	public function getShares($mCalendarId) {
		
//		$fields = implode(', ', $this->sharesProperties);
		$stmt = $this->pdo->prepare("SELECT * FROM ".$this->calendarSharesTableName." AS calendarShares  WHERE calendarShares.calendarid = ? ORDER BY calendarShares.calendarid ASC");
		$stmt->execute(array($mCalendarId));

		$aShares = array();
		
		while($aRow = $stmt->fetch(\PDO::FETCH_ASSOC)) { 
			
			$sCommonName = basename($aRow['principaluri']);
			$oAccount = \Afterlogic\DAV\Utils::GetAccountByLogin($sCommonName);
			if ($oAccount instanceof \CAccount) {
				$sCommonName = $oAccount->FriendlyName;
			}
			$aShare = array(	
				'calendarid' => $aRow['calendarid'],
				'principalpath' => $aRow['principaluri'],
				'readOnly' => $aRow['readonly'],
				'summary' => $aRow['summary'],
				'href' => basename($aRow['principaluri']),
				'commonName' => $sCommonName,
				'displayname' => $aRow['displayname'],
				'status' => $aRow['status'],
				'color' => $aRow['color']
			);
			
			// add it to main array
			$aShares[] = $aShare;
		}
	
		return $aShares;
	}

	protected function getCalendarFields()
	{
		$aFields = array_values($this->propertyMap);
		
        $aFields[] = 'id';
        $aFields[] = 'uri';
        $aFields[] = 'synctoken';
        $aFields[] = 'components';
        $aFields[] = 'principaluri';
        $aFields[] = 'transparent';
		

		// Making fields a comma-delimited list
		return implode(', ', $aFields);		
	}
	
	/**
	 * Returns a list of calendars for a principal.
	 *
	 * Every project is an array with the following keys:
	 *  * id, a unique id that will be used by other functions to modify the
	 *    calendar. This can be the same as the uri or a database key.
	 *  * uri, which the basename of the uri with which the calendar is
	 *    accessed.
	 *  * principaluri. The owner of the calendar. Almost always the same as
	 *    principalUri passed to this method.
	 *
	 * Furthermore it can contain webdav properties in clark notation. A very
	 * common one is '{DAV:}displayname'.
	 * 
	 * MODIFIED: THIS METHOD NOW NEEDS TO BE ABLE TO RETRIEVE SHARED CALENDARS
	 *
	 * @param string $principalUri
	 * @return array
	 */
	public function getCalendarsForUser($principalUri) {
	
		$aCalendars = $this->getOwnCalendarsForUser($principalUri);
		
		if (count($aCalendars) === 0) {
			$sDisplayname = \Afterlogic\DAV\Constants::CALENDAR_DEFAULT_NAME;
			$oCalendarDecorator = \Aurora\System\Api::GetModuleDecorator('Calendar');
			
			if ($oCalendarDecorator)
			{
				$sPublicId = basename($principalUri);
				$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sPublicId);
				if ($oUser)
				{
					$sDisplayname = $oCalendarDecorator->i18N('CALENDAR_DEFAULT_NAME', null, null, $oUser->UUID);
				}
			}

			$this->createCalendar(
				$principalUri, 
				\Sabre\DAV\UUIDUtil::getUUID(), 
				[
					'{DAV:}displayname' => $sDisplayname,
					'{'.\Sabre\CalDAV\Plugin::NS_CALENDARSERVER.'}getctag' => 1,
					'{'.\Sabre\CalDAV\Plugin::NS_CALDAV.'}calendar-description' => '',
					'{http://apple.com/ns/ical/}calendar-color' => \Afterlogic\DAV\Constants::CALENDAR_DEFAULT_COLOR,
					'{http://apple.com/ns/ical/}calendar-order' => 0
				]
			);
			$aCalendars = $this->getOwnCalendarsForUser($principalUri);
		}
		
		
		$aSharedCalendars = $this->getSharedCalendarsForUser($principalUri);
/*		
		$aSharedToAllCalendars = $this->getSharedCalendarsForUser(
				\Afterlogic\DAV\Utils::getTenantPrincipalUri($principalUri)
		);
		
		foreach ($aSharedToAllCalendars as $iKey => $aSharedToAllCalendar) {
			
			if (isset($aCalendars[$iKey])) {
				
				$aSharedToAllCalendar['{http://sabredav.org/ns}read-only'] = false;
			}
			$aCalendars[$iKey] = $aSharedToAllCalendar;
		}
		foreach ($aSharedCalendars as $iKey => $aSharedCalendar) {
			
			if (isset($aCalendars[$iKey])) {
				
				$aSharedCalendar['{http://sabredav.org/ns}read-only'] = false;
			}
			$aCalendars[$iKey] = $aSharedCalendar;
		}
		$aSharedCalendars = array();
*/		
		return array_merge($aCalendars, $aSharedCalendars);
	}
	
	protected function getOwnCalendarsForUser($principalUri)
	{
		$sFields = $this->getCalendarFields();
		$oStmt = $this->pdo->prepare("SELECT " . $sFields . " FROM ".$this->calendarTableName." WHERE principaluri = ? ORDER BY id ASC");
		$oStmt->execute(array($principalUri));

		$aCalendars = array();
		while($aRows = $oStmt->fetch(\PDO::FETCH_ASSOC)) {
			
			$aComponents = (isset($aRows['components'])) ? explode(',', $aRows['components']) : array();

			$aCalendar = array(
                'id' => $aRows['id'],
                'uri' => $aRows['uri'],
                'principaluri' => $aRows['principaluri'],
                '{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag' => $aRows['synctoken'] ? $aRows['synctoken'] : '0',
                '{http://sabredav.org/ns}sync-token' => 'http://sabre.io/ns/sync/' . ($aRows['synctoken'] ? $aRows['synctoken'] : '0'),
                '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet($aComponents),
                '{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp' => new \Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp($aRows['transparent'] ? 'transparent' : 'opaque'),
			);
			foreach($this->propertyMap as $xmlName => $dbName) {
				$aCalendar[$xmlName] = $aRows[$dbName];
			}

			$aCalendars[$aCalendar['id']] = $aCalendar;
		}		
		
		return $aCalendars;
	}
	
	protected function getSharedCalendarsForUser($sPrincipalUri, $bSharedToAll = false)
	{
		$aCalendars = array();
		$sTenantPrincipalUri = null;
		
		if (!$sPrincipalUri) {
			
			return $aCalendars;
		}
		
		if ($sPrincipalUri) {
			
			if ($bSharedToAll) {
				$sTenantPrincipalUri = $sPrincipalUri;
				$sPrincipalUri = \Afterlogic\DAV\Utils::getTenantPrincipalUri($sPrincipalUri);
			}
			
			$sFields = $this->getCalendarFields();
			$sShareFields = implode(', ', $this->sharesProperties);

			$oStmt = $this->pdo->prepare("SELECT " . $sShareFields . " FROM ".$this->calendarSharesTableName." WHERE principaluri = ?");

			$oStmt->execute(array($sPrincipalUri));
			$aRows = $oStmt->fetchAll(\PDO::FETCH_ASSOC);

			foreach ($aRows as $aRow) {
				
				// get the original calendar
				$oCalStmt = $this->pdo->prepare("SELECT " . $sFields . " FROM ".$this->calendarTableName." WHERE id = ? ORDER BY id ASC LIMIT 1");
				$oCalStmt->execute(array($aRow['calendarid']));

				while($calRow = $oCalStmt->fetch(\PDO::FETCH_ASSOC)) {
					
					$aComponents = (isset($aRows['components'])) ? explode(',', $aRows['components']) : array();

					$aCalendar = array(
						'id' => $calRow['id'],
						'uri' => $calRow['uri'],
						'principaluri' => $bSharedToAll ? $sTenantPrincipalUri : $sPrincipalUri,
//							'{' . \Sabre\CalDAV\Plugin::NS_CALENDARSERVER . '}getctag' => $calRow['ctag'] ? $calRow['ctag'] : '0',
						'{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}supported-calendar-component-set' => new \Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet($aComponents),
						'{' . \Sabre\CalDAV\Plugin::NS_CALDAV . '}schedule-calendar-transp' => new \Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp($calRow['transparent'] ? 'transparent' : 'opaque'),
					);
					// some specific properies for shared calendars
					$aCalendar['{http://calendarserver.org/ns/}shared-url'] = $calRow['uri'];
					$aCalendar['{http://sabredav.org/ns}owner-principal'] = $calRow['principaluri'];
					$aCalendar['{http://sabredav.org/ns}read-only'] = $aRow['readonly'];
					$aCalendar['{http://calendarserver.org/ns/}summary'] = $aRow['summary'];

					foreach($this->propertyMap as $xmlName=>$dbName)  {
						
						if($xmlName == '{DAV:}displayname') { 
							
							$aCalendar[$xmlName] = $calRow['displayname'];//$aRow['displayname'] == null ? $calRow['displayname'] : $aRow['displayname'];
						} 
						elseif($xmlName == '{http://apple.com/ns/ical/}calendar-color') {
							
							$aCalendar[$xmlName] = $aRow['color'] == null ? $calRow['calendarcolor'] : $aRow['color'];
						} else {
							
							$aCalendar[$xmlName] = $calRow[$dbName];
						}
					}

					$aCalendars[$aCalendar['id']] = $aCalendar;
				}
			}
		}		
		
		return $aCalendars; 
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
