<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Backend;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class PDO
{
	/**
	 * @var string
	 */
	protected $dBPrefix;

	/**
	 * @var \PDO
	 */
	protected $pdo;

	/**
	 * @var string
	 */
	protected $sharedFilesTableName;

	/**
	 * @var string
	 */
	protected $filesChangesTableName;

	/**
	 * @var string
	 */
	protected $filesStoragesTableName;

	/**
	 * Creates the backend
	 */
	public function __construct()
	{
		$this->pdo = \Aurora\System\Api::GetPDO();
		$oSettings = \Aurora\System\Api::GetSettings();
		if ($oSettings)
		{
			$this->dBPrefix = \Aurora\System\Api::GetSettings()->DBPrefix;
		}
		$this->sharedFilesTableName = $this->dBPrefix.'adav_sharedfiles';
		$this->filesChangesTableName = $this->dBPrefix.'adav_files_changes';
		$this->filesStoragesTableName = $this->dBPrefix.'adav_files_storages';
	}

    /* @param string $principalUri
     * @return array
     */
    public function getSharedFilesForUser($principalUri, $sharePath = null) {

		$aResult = [];

		$fields[] = 'id';
        $fields[] = 'owner';
        $fields[] = 'principaluri';
        $fields[] = 'storage';
        $fields[] = 'path';
        $fields[] = 'uid';
        $fields[] = 'access';
        $fields[] = 'isdir';
		$fields[] = 'share_path';
		$fields[] = 'group_id';
		$fields[] = 'initiator';
		$fields[] = 'Properties';

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
		if ($sharePath === null) {
 	       $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.principaluri = ? AND ({$this->sharedFilesTableName}.share_path IS NULL OR {$this->sharedFilesTableName}.share_path = '')
SQL
        	);

			$stmt->execute([$principalUri]);
		} else {
 	       $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.principaluri = ? AND {$this->sharedFilesTableName}.share_path = ?
SQL
        	);

			$stmt->execute([$principalUri, $sharePath]);			
		}
		while($row = $stmt->fetch(\PDO::FETCH_ASSOC))
		{
			$aResult[] = [
				'id' => $row['id'],
				'uid' => $row['uid'],
				'owner' => $row['owner'],
				'principaluri' => $row['principaluri'],
				'storage' => $row['storage'],
				'path' => $row['path'],
				'access' => (int) $row['access'],
				'isdir' => (bool) $row['isdir'],
				'share_path' => $row['share_path'],
				'group_id' => $row['group_id'],
				'initiator' => $row['initiator'],
				'properties' => $row['Properties'],
			];
		}

		return $aResult;
	}

    /* @param string $principalUri
    /* @param string $path
     * @return array
     */
    public function getSharedFile($principalUri, $path) {

		$aResult = false;

		$fields[] = 'id';
        $fields[] = 'owner';
        $fields[] = 'principaluri';
        $fields[] = 'storage';
        $fields[] = 'path';
        $fields[] = 'uid';
        $fields[] = 'access';
        $fields[] = 'isdir';
		$fields[] = 'share_path';
		$fields[] = 'group_id';
		$fields[] = 'initiator';
		$fields[] = 'Properties';

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.principaluri = ? AND path = ?
SQL
        );

		$stmt->execute([$principalUri, $path]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

		if ($row)
		{
			$aResult = [
				'id' => $row['id'],
				'uid' => $row['uid'],
				'owner' => $row['owner'],
				'principaluri' => $row['principaluri'],
				'storage' => $row['storage'],
				'path' => $row['path'],
				'access' => (int) $row['access'],
				'isdir' => (bool) $row['isdir'],
				'share_path' => $row['share_path'],
				'group_id' => $row['group_id'],
				'initiator' => $row['initiator'],
				'properties' => $row['Properties'],
			];
		}

		return $aResult;
	}
	
    /* @param string $principalUri
    /* @param string $uid
     * @return array
     */
    public function getSharedFileBySharePath($principalUri, $sharePath = '') {

		$aResult = false;

		$fields[] = 'id';
        $fields[] = 'owner';
        $fields[] = 'principaluri';
        $fields[] = 'storage';
        $fields[] = 'path';
        $fields[] = 'uid';
        $fields[] = 'access';
        $fields[] = 'isdir';
		$fields[] = 'share_path';
		$fields[] = 'group_id';

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.principaluri = ? AND (share_path = ? OR share_path LIKE ?)
SQL
        );

		$stmt->execute([$principalUri, $sharePath, $sharePath . '/%']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

		if ($row)
		{
			$aResult = [
				'id' => 0,
				'uid' => $sharePath,
				'owner' => $row['owner'],
				'principaluri' => $row['principaluri'],
				'storage' => 'shared',
				'path' => $sharePath,
				'access' => 2,
				'isdir' => true,
				'share_path' => '',
				'group_id' => $row['group_id'],
			];
		}

		return $aResult;
	}

    /* @param string $principalUri
    /* @param string $uid
     * @return array
     */
    public function getSharedFileByUidWithPath($principalUri, $uid, $sharePath = '', $bWithoutGroup = false) {

		$aResult = false;

		$fields[] = 'id';
        $fields[] = 'owner';
        $fields[] = 'principaluri';
        $fields[] = 'storage';
        $fields[] = 'path';
        $fields[] = 'uid';
        $fields[] = 'access';
        $fields[] = 'isdir';
		$fields[] = 'share_path';
		$fields[] = 'group_id';

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);

		$sWithoutGroup = $bWithoutGroup ? 'AND group_id = 0' : '';

        $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.principaluri = ? AND uid = ? AND share_path = ? {$sWithoutGroup}
SQL
        );

		$stmt->execute([$principalUri, $uid, $sharePath]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

		if ($row)
		{
			$aResult = [
				'id' => $row['id'],
				'uid' => $row['uid'],
				'owner' => $row['owner'],
				'principaluri' => $row['principaluri'],
				'storage' => $row['storage'],
				'path' => $row['path'],
				'access' => (int) $row['access'],
				'isdir' => (bool) $row['isdir'],
				'share_path' => $row['share_path'],
				'group_id' => $row['group_id'],
			];
		}

		return $aResult;
	}

	/* @param string $principalUri
    /* @param string $uid
     * @return array
     */
    public function getSharedFileByUid($principalUri, $uid) {

		$aResult = false;

		$fields[] = 'id';
        $fields[] = 'owner';
        $fields[] = 'principaluri';
        $fields[] = 'storage';
        $fields[] = 'path';
        $fields[] = 'uid';
        $fields[] = 'access';
        $fields[] = 'isdir';
		$fields[] = 'share_path';
		$fields[] = 'group_id';

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.principaluri = ? AND uid = ?
SQL
        );

		$stmt->execute([$principalUri, $uid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
		if ($row) {
			$aResult = [
				'id' => $row['id'],
				'uid' => $row['uid'],
				'owner' => $row['owner'],
				'principaluri' => $row['principaluri'],
				'storage' => $row['storage'],
				'path' => $row['path'],
				'access' => (int) $row['access'],
				'isdir' => (bool) $row['isdir'],
				'share_path' => $row['share_path'],
				'group_id' => $row['group_id'],
			];
		}

		return $aResult;
	}

		/* @param string $principalUri
    /* @param string $uid
     * @return array
     */
    public function getSharedFilesByUid($principalUri, $uid, $share_path = '') {

		$aResult = [];

		$fields[] = 'id';
        $fields[] = 'owner';
        $fields[] = 'principaluri';
        $fields[] = 'storage';
        $fields[] = 'path';
        $fields[] = 'uid';
        $fields[] = 'access';
        $fields[] = 'isdir';
		$fields[] = 'share_path';
		$fields[] = 'group_id';
		$fields[] = 'initiator';
		$fields[] = 'Properties';

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.principaluri = ? AND uid = ? AND share_path = ?
SQL
        );

		$stmt->execute([$principalUri, $uid, $share_path]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			if ($row) {
				$aResult[] = [
					'id' => $row['id'],
					'uid' => $row['uid'],
					'owner' => $row['owner'],
					'principaluri' => $row['principaluri'],
					'storage' => $row['storage'],
					'path' => $row['path'],
					'access' => (int) $row['access'],
					'isdir' => (bool) $row['isdir'],
					'share_path' => $row['share_path'],
					'group_id' => $row['group_id'],
					'initiator' => $row['initiator'],
					'properties' => $row['Properties'],
				];
			}
		}

		return $aResult;
	}

	/* @param string $owner
	 * @param string $storage
	 * @param string $path
     * @return array
     */
    public function getShares($owner, $storage, $path) {

		$aResult = [];

		$fields = [
			'id',
        	'owner',
        	'storage',
        	'path',
       		'principaluri',
        	'access',
			'share_path',
			'group_id'
		];

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.owner = ? AND {$this->sharedFilesTableName}.storage = ? AND {$this->sharedFilesTableName}.path = ?
SQL
        );

		$stmt->execute([$owner, $storage, $path]);
		while($row = $stmt->fetch(\PDO::FETCH_ASSOC))
		{
			$aResult[] = [
				'id' => (int) $row['id'],
				'principaluri' => $row['principaluri'],
				'access' => (int) $row['access'],
				'share_path' => $row['share_path'],
				'group_id' => (int) $row['group_id'],
			];
		}

		return $aResult;
	}

	/* @param string $owner
	 * @param string $storage
	 * @param string $path
     * @return array
     */
    public function getSharesByGroupId($groupId) {

		$aResult = [];

		$fields = [
        	'owner',
        	'storage',
        	'path',
        	'access',
			'isdir',
			'group_id'
		];

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(<<<SQL
SELECT DISTINCT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.group_id = ?
SQL
        );

		$stmt->execute([$groupId]);
		while($row = $stmt->fetch(\PDO::FETCH_ASSOC))
		{
			$aResult[] = [
				'owner' => $row['owner'],
				'storage' => $row['storage'],
				'access' => (int) $row['access'],
				'path' => $row['path'],
				'isdir' => (int) $row['isdir'],
				'group_id' => (int) $row['group_id'],
			];
		}

		return $aResult;
	}

	public function getSharesByPrincipalUriAndGroupId($principalUri, $groupId) {

		$aResult = [];

        $stmt = $this->pdo->prepare(<<<SQL
SELECT DISTINCT storage, path, owner, access, isdir, group_id, initiator
FROM {$this->sharedFilesTableName}
WHERE group_id = ? AND ? NOT IN (SELECT principaluri FROM {$this->sharedFilesTableName} WHERE group_id = ?) 
SQL
        );

		$stmt->execute([$groupId, $principalUri, $groupId]);
		while($row = $stmt->fetch(\PDO::FETCH_ASSOC))
		{
			$aResult[] = [
				'owner' => $row['owner'],
				'storage' => $row['storage'],
				'access' => (int) $row['access'],
				'path' => $row['path'],
				'isdir' => (int) $row['isdir'],
				'group_id' => (int) $row['group_id'],
				'initiator' => $row['initiator'],
			];
		}

		return $aResult;
	}

	/**
	 *
	 * @param string $owner
	 * @param string $storage
	 * @param string $path
	 * @param string $uid
	 * @param string $principalUri
	 * @param bool $access
	 * @param bool $isdir
	 * @return int
	 */
	public function createSharedFile($owner, $storage, $path, $uid, $principalUri, $access, $isdir, $share_path = '', $group_id = 0, $initiator = '')
	{
		$values = $fieldNames = [];
        $fieldNames[] = 'owner';
		$values[':owner'] = $owner;

		$fieldNames[] = 'storage';
		$values[':storage'] = $storage;

		$fieldNames[] = 'path';
		$values[':path'] = $path;

		$fieldNames[] = 'uid';
		$values[':uid'] = $uid;

		$fieldNames[] = 'principaluri';
		$values[':principaluri'] = $principalUri;

		$fieldNames[] = 'access';
		$values[':access'] = (int) $access;

		$fieldNames[] = 'isdir';
		$values[':isdir'] = (int) $isdir;

		$fieldNames[] = 'share_path';
		$values[':share_path'] = $share_path;

		if (isset($group_id)) {
			$fieldNames[] = 'group_id';
			$values[':group_id'] = $group_id;			
		}

		$fieldNames[] = 'initiator';
		$values[':initiator'] = $initiator;

		$stmt = $this->pdo->prepare("INSERT INTO ".$this->sharedFilesTableName." (".implode(', ', $fieldNames).") VALUES (".implode(', ',array_keys($values)).")");
        $stmt->execute($values);

        return $this->pdo->lastInsertId();
	}

	public function updateSharedFile($owner, $storage, $path, $principalUri, $access, $groupId = 0, $initiator = '')
	{
		if (!empty($initiator)) {
			$initiator = ', initiator = ' . $this->pdo->quote($initiator);
		}
		$stmt = $this->pdo->prepare('UPDATE ' . $this->sharedFilesTableName . ' SET access = ?' . $initiator . ' WHERE owner = ? AND principaluri = ? AND storage = ? AND path = ? AND group_id = ?');
		return  $stmt->execute([$access, $owner, $principalUri, $storage, $path, $groupId]);
	}

	public function updateSharedFileName($principalUri, $uid, $name, $share_path = '', $group_id = 0)
	{
		$stmt = $this->pdo->prepare('UPDATE ' . $this->sharedFilesTableName . ' SET `uid` = ? WHERE principaluri = ? AND uid = ? AND share_path = ? AND group_id = ?');
		return  $stmt->execute([$name, $principalUri, $uid, $share_path, $group_id]);
	}

	public function updateSharedFileSharePath($principalUri, $uid, $sharePath, $newSharePath, $group_id = 0)
	{
		$stmt = $this->pdo->prepare('UPDATE ' . $this->sharedFilesTableName . ' SET `share_path` = ? WHERE principaluri = ? AND uid = ? AND share_path = ? AND group_id = ?');
		return  $stmt->execute([$newSharePath, $principalUri, $uid, $sharePath, $group_id]);
	}

	public function updateSharedFileSharePathWithLike($principalUri, $sharePath, $newSharePath, $group_id = 0)
	{
		$stmt = $this->pdo->prepare(
			'UPDATE ' . $this->sharedFilesTableName . '
			SET share_path = REPLACE(share_path, ?, ?)
			WHERE principaluri = ? AND share_path LIKE ? AND group_id = ?'
		);
		return  $stmt->execute([$sharePath, $newSharePath, $principalUri, $sharePath . '%', $group_id]);
	}

	public function updateShare($owner, $storage, $path,  $newStorage, $newPath)
	{
		$stmt = $this->pdo->prepare(
			"UPDATE " . $this->sharedFilesTableName . "
			SET path = REPLACE(path, ?, ?), storage = ?
			WHERE path LIKE ? AND owner = ? AND storage = ?"
		);
		return  $stmt->execute([$path, $newPath, $newStorage, $path . '%', $owner, $storage]);
	}

	/**
	 *
	 * @param string $owner
	 * @param string $storage
	 * @param string $path
	 * @return bool
	 */
	public function deleteSharedFile($owner, $storage, $path)
	{
		$result = false;
        $stmt = $this->pdo->prepare('DELETE FROM ' . $this->sharedFilesTableName . ' WHERE owner = ? AND storage = ? AND path = ?');
        if ($stmt->execute([$owner, $storage, $path])) {
			$stmt = $this->pdo->prepare('DELETE FROM ' . $this->sharedFilesTableName . ' WHERE owner = ? AND storage = ? AND path LIKE ?');
			$result = $stmt->execute([$owner, $storage, $path . '/%']);
		}

		return $result;
	}

		/**
	 *
	 * @param string $principaluri
	 * @param string $storage
	 * @param string $path
	 * @return bool
	 */
	public function deleteSharedFileByPrincipalUri($principaluri, $storage, $path, $group_id = 0)
	{
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->sharedFilesTableName.' WHERE principaluri = ? AND storage = ? AND path = ? AND group_id = ?');
        return $stmt->execute([$principaluri, $storage, $path, $group_id]);
	}

	/**
	 *
	 * @param string $owner
	 * @param string $path
	 * @return bool
	 */
	public function deleteShare($principaluri, $uid, $share_path = '')
	{
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->sharedFilesTableName.' WHERE principaluri = ? AND uid = ? AND share_path = ?');
        return $stmt->execute([$principaluri, $uid, $share_path]);
	}

	/**
	 *
	 * @param string $principalUri
	 * @return bool
	 */
	public function deleteSharesByPrincipal($principalUri)
	{
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->sharedFilesTableName.' WHERE principaluri = ? OR owner = ?');
        return $stmt->execute([$principalUri, $principalUri]);
	}

	/**
	 *
	 * @param string $owner
	 * @param string $path
	 * @param array $groupIds
	 * @return bool
	 */
	public function deleteShareByGroupIds($principaluri, $storage, $uid, $groupIds)
	{
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->sharedFilesTableName.' WHERE principaluri = ? AND storage = ? AND uid = ? AND group_id in (?)');
        return $stmt->execute([$principaluri, $storage, $uid, implode(',', $groupIds)]);
	}

		/**
	 *
	 * @param string $owner
	 * @param string $path
	 * @param array $groupIds
	 * @return bool
	 */
	public function deleteShareByPrincipaluriAndGroupId($principaluri, $groupId)
	{
        $stmt = $this->pdo->prepare('DELETE FROM ' . $this->sharedFilesTableName . ' WHERE principaluri = ? AND group_id = ?');
        return $stmt->execute([$principaluri, $groupId]);
	}

	public function deleteShareNotInGroups($principaluri, $groupIds) {
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->sharedFilesTableName.' WHERE group_id NOT IN (' . implode(', ', $groupIds) . ') AND principaluri = ? AND group_id > 0');
        return $stmt->execute([$principaluri]);
	}

	public function deleteSharesByGroupId($groupId)
	{
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->sharedFilesTableName.' WHERE group_id = ?');
        return $stmt->execute([$groupId]);
	}

	/**
     * The getChanges method returns all the changes that have happened, since
     * the specified syncToken in the specified file storage.
     *
     * This function should return an array, such as the following:
     *
     * [
     *   'syncToken' => 'The current synctoken',
     *   'added'   => [
     *      'new.txt',
     *   ],
     *   'modified'   => [
     *      'modified.txt',
     *   ],
     *   'deleted' => [
     *      'foo.php.bak',
     *      'old.txt'
     *   ]
     * ];
     *
     * The returned syncToken property should reflect the *current* syncToken
     * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
     * property this is needed here too, to ensure the operation is atomic.
     *
     * If the $syncToken argument is specified as null, this is an initial
     * sync, and all members should be reported.
     *
     * The modified property is an array of nodenames that have changed since
     * the last token.
     *
     * The deleted property is an array with nodenames, that have been deleted
     * from collection.
     *
     * The $syncLevel argument is basically the 'depth' of the report. If it's
     * 1, you only have to report changes that happened only directly in
     * immediate descendants. If it's 2, it should also include changes from
     * the nodes below the child collections. (grandchildren)
     *
     * The $limit argument allows a client to specify how many results should
     * be returned at most. If the limit is not specified, it should be treated
     * as infinite.
     *
     * If the limit (infinite or not) is higher than you're willing to return,
     * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
     *
     * If the syncToken is expired (due to data cleanup) or unknown, you must
     * return null.
     *
     * The limit is 'suggestive'. You are free to ignore it.
     *
     * @param mixed  $storage
     * @param string $syncToken
     * @param int    $syncLevel
     * @param int    $limit
     *
     * @return array
     */
    public function getChanges($principaluri, $storage , $syncToken, $syncLevel, $limit = null)
    {
        $result = [
            'added' => [],
            'modified' => [],
            'deleted' => [],
        ];

        if ($syncToken) {
            $query = 'SELECT uri, operation, synctoken FROM '.$this->filesChangesTableName.' WHERE synctoken >= ?  AND principaluri = ? AND storage = ? ORDER BY synctoken';
            if ($limit > 0) {
                // Fetch one more raw to detect result truncation
                $query .= ' LIMIT '.((int) $limit + 1);
            }

            // Fetching all changes
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$syncToken, $principaluri, $storage]);

            $changes = [];

            // This loop ensures that any duplicates are overwritten, only the
            // last change on a node is relevant.
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $changes[\ltrim($row['uri'], '/')] = $row;
            }
            $currentToken = null;

            $result_count = 0;
            foreach ($changes as $uri => $operation) {
                if (!is_null($limit) && $result_count >= $limit) {
                    $result['result_truncated'] = true;
                    break;
                }

                if (null === $currentToken || $currentToken < $operation['synctoken'] + 1) {
                    // SyncToken in CalDAV perspective is consistently the next number of the last synced change event in this class.
                    $currentToken = $operation['synctoken'] + 1;
                }

                ++$result_count;
                switch ($operation['operation']) {
                    case 1:
                        $result['added'][] = $uri;
                        break;
                    case 2:
                        $result['modified'][] = $uri;
                        break;
                    case 3:
                        $result['deleted'][] = $uri;
                        break;
                }
            }

            if (!is_null($currentToken)) {
                $result['syncToken'] = $currentToken;
            } else {
                // This means returned value is equivalent to syncToken
                $result['syncToken'] = $syncToken;
            }
        } else {
			$currentToken = $this->getSyncToken($principaluri, $storage);

            if (is_null($currentToken)) {
                return null;
            }
            $result['syncToken'] = $currentToken;
        }

        return $result;
    }

	public function getSyncToken($principaluri, $storage)
	{
		$stmt = $this->pdo->prepare('SELECT synctoken FROM '.$this->filesStoragesTableName.' WHERE principaluri = ? AND storage = ?');
		$stmt->execute([$principaluri, $storage]);
		$currentToken = $stmt->fetchColumn(0);

		if (!$currentToken) {
			$currentToken = 1;
			$stmt = $this->pdo->prepare('INSERT INTO '.$this->filesStoragesTableName.' (principaluri, storage, synctoken) VALUES (?, ?, ?)');
			$stmt->execute([
				$principaluri,
				$storage,
				$currentToken
			]);
		}
		return $currentToken;
	}

    /**
     * Adds a change record to the calendarchanges table.
     *
     * @param mixed  $principaluri
     * @param mixed  $storage
     * @param string $objectUri
     * @param int    $operation  1 = add, 2 = modify, 3 = delete
     */
    public function addChange($principaluri, $storage, $objectUri, $operation)
    {
        $stmt = $this->pdo->prepare('INSERT INTO '.$this->filesChangesTableName.' (uri, synctoken, principaluri, storage, operation) SELECT ?, synctoken, ?, ?, ? FROM '.$this->filesStoragesTableName.' WHERE principaluri = ? AND storage = ?');
        $stmt->execute([
            $objectUri,
            $principaluri,
            $storage,
            $operation,
			$principaluri,
            $storage
        ]);
        $stmt = $this->pdo->prepare('UPDATE '.$this->filesStoragesTableName.' SET synctoken = synctoken + 1 WHERE principaluri = ? AND storage = ?');
        $stmt->execute([
            $principaluri,
			$storage
        ]);
    }

}
