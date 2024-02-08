<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV\Backend;

use Afterlogic\DAV\Constants;
use Sabre\DAV\Xml\Element\Sharee;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
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

		$this->dBPrefix = \Aurora\System\Api::GetSettings()->DBPrefix;

		$this->calendarTableName = $this->dBPrefix.Constants::T_CALENDARS;
		$this->calendarChangesTableName = $this->dBPrefix.Constants::T_CALENDARCHANGES;
		$this->calendarObjectTableName = $this->dBPrefix . Constants::T_CALENDAROBJECTS;
		$this->schedulingObjectTableName = $this->dBPrefix . Constants::T_SCHEDULINGOBJECTS;
		$this->calendarSubscriptionsTableName = $this->dBPrefix . Constants::T_CALENDARSUBSCRIPTIONS;
		$this->calendarInstancesTableName = $this->dBPrefix . Constants::T_CALENDARINSTANCES;

	}

	public function createCalendar($principalUri, $calendarUri, array $properties) {

		$sOrderProp = '{http://apple.com/ns/ical/}calendar-order';
		if (!isset($properties[$sOrderProp]))
		{
			$properties[$sOrderProp] = 1;
		}

		return parent::createCalendar($principalUri, $calendarUri, $properties);
	}

	public function deletePrincipalCalendars($principalUri)
	{
		$bResult = false;
		$stmt = $this->pdo->prepare('SELECT calendarid, id FROM ' . $this->calendarInstancesTableName . ' where principaluri = ?');
        $stmt->execute([$principalUri]);
		$aCalendars = $stmt->fetchAll(\PDO::FETCH_NUM);
		foreach ($aCalendars as $aCalendar)
		{
			$bResult = $this->deleteCalendar($aCalendar);
		}

		return $bResult;
	}

	protected function getTenantPrincipal($sUserPublicId)
	{
		$sTenantPrincipal = 'default_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
		if ($oUser)
		{
			$sTenantPrincipal = $oUser->IdTenant . '_' . \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL;
		}

		return \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $sTenantPrincipal;
	}

    public function getPublicCalendar($calendarId) {

		$calendar = false;

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
WHERE access = 1 AND {$this->calendarInstancesTableName}.uri = ? AND public = 1 ORDER BY calendarorder ASC
SQL
        );

		$stmt->execute([$calendarId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
		if ($row)
		{
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

			$row['access'] = \Sabre\DAV\Sharing\Plugin::ACCESS_READ;
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
		}

        return $calendar;
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

		if ($row) {
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
		}

        return $calendar;
    }

	public function getCalendarIdByUri($uri) 
	{
		$stmt = $this->pdo->prepare(<<<SQL
		SELECT {$this->calendarInstancesTableName}.calendarid FROM {$this->calendarInstancesTableName}
		WHERE {$this->calendarInstancesTableName}.uri = ? ORDER BY calendarorder ASC
		SQL
		);
		
		$stmt->execute([$uri]);
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);

		if ($row) {
			return $row['calendarid'];
		}

		return false; 
	}

	public function getParentCalendarByUri($calendarUri) {

		$calendar = false;

		$calendarId = $this->getCalendarIdByUri($calendarUri);
		if ($calendarId) {
			$calendar = $this->getParentCalendar($calendarId);
		}

        return $calendar;
    }

	public function getChildrenCalendarIds($calendarId) {
		
        if (!is_array($calendarId)) {
            throw new \InvalidArgumentException('The value passed to $calendarId is expected to be an array with a calendarId and an instanceId');
        }
        list($calendarId, $instanceId) = $calendarId;

		$stmt = $this->pdo->prepare(<<<SQL
	SELECT {$this->calendarInstancesTableName}.id as id, calendarid FROM {$this->calendarInstancesTableName}
	LEFT JOIN {$this->calendarTableName} ON
		{$this->calendarInstancesTableName}.calendarid = {$this->calendarTableName}.id
	WHERE {$this->calendarTableName}.id = ? AND {$this->calendarInstancesTableName}.access <> 1 ORDER BY calendarorder ASC
	SQL
		);
		$stmt->execute([$calendarId]);

		$calendars = [];
		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {	
			$calendars[] = [(int) $row['calendarid'], (int) $row['id']];
		}

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
     * Returns the list of people whom a calendar is shared with.
     *
     * Every item in the returned list must be a Sharee object with at
     * least the following properties set:
     *   $href
     *   $shareAccess
     *   $inviteStatus
     *
     * and optionally:
     *   $properties
     *
     * @param mixed $calendarId
     *
     * @return \Sabre\DAV\Xml\Element\Sharee[]
     */
    public function getInvites($calendarId)
    {
        if (!is_array($calendarId)) {
            throw new \InvalidArgumentException('The value passed to getInvites() is expected to be an array with a calendarId and an instanceId');
        }
        list($calendarId, $instanceId) = $calendarId;

        $query = <<<SQL
SELECT
    principaluri,
    access,
    share_href,
    share_displayname,
    share_invitestatus
FROM {$this->calendarInstancesTableName}
WHERE
    calendarid = ?
SQL;

        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$calendarId]);

        $result = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = new Sharee([
                'href' => isset($row['share_href']) ? $row['share_href'] : 'mailto:' . \Sabre\HTTP\encodePath(basename($row['principaluri'])),
                'access' => (int) $row['access'],
                /// Everyone is always immediately accepted, for now.
                'inviteStatus' => (int) $row['share_invitestatus'],
                'properties' => !empty($row['share_displayname'])
                    ? ['{DAV:}displayname' => $row['share_displayname']]
                    : [],
                'principal' => $row['principaluri'],
            ]);
        }

        return $result;
    }

	/**
	 * Marks this calendar as published.
	 *
	 * Publishing a calendar should automatically create a read-only, public,
	 * subscribable calendar.
	 *
	 * @param bool $value
	 * @return void
	 */
	public function setPublishStatus($calendarUri, $value, $oUser = null)
	{
        $bResult = false;
		$oUser = $oUser ? $oUser : \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser)
		{
			$stmt = $this->pdo->prepare('UPDATE ' . $this->calendarInstancesTableName . ' SET `public` = ? WHERE principaluri = ? AND uri = ?');
			$bResult =  $stmt->execute([(int)$value, \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $oUser->PublicId, $calendarUri]);
		}

		return $bResult;
	}

	/**
	 * Marks this calendar as published.
	 *
	 * Publishing a calendar should automatically create a read-only, public,
	 * subscribable calendar.
	 *
	 * @return void
	 */
	public function getPublishStatus($calendarUri)
	{
        $bResult = false;
		$oUser = \Aurora\System\Api::getAuthenticatedUser();
		if ($oUser)
		{
			$stmt = $this->pdo->prepare('SELECT public FROM ' . $this->calendarInstancesTableName . ' WHERE principaluri = ? AND uri = ?');
			$stmt->execute([\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $oUser->PublicId, $calendarUri]);
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			if ($row)
			{
				$bResult = (bool) $row['public'];
			}
		}

		return $bResult;
	}

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
