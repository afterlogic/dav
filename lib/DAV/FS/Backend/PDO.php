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

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
		if ($sharePath === null) {
 	       $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.principaluri = ?
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
				'is_share_path'
			];
		}

		return $aResult;
	}

    /* @param string $principalUri
    /* @param string $uid
     * @return array
     */
    public function getSharedFileByUid($principalUri, $uid, $sharePath = '') {

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

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.principaluri = ? AND uid = ? AND share_path = ?
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
			];
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

		$fields[] = 'id';
        $fields[] = 'owner';
        $fields[] = 'storage';
        $fields[] = 'path';
        $fields[] = 'principaluri';
        $fields[] = 'access';
		$fields[] = 'share_path';

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
				'id' => $row['id'],
				'principaluri' => $row['principaluri'],
				'access' => (int) $row['access'],
				'share_path' => $row['share_path'],
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
	public function createSharedFile($owner, $storage, $path, $uid, $principalUri, $access, $isdir)
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

		$stmt = $this->pdo->prepare("INSERT INTO ".$this->sharedFilesTableName." (".implode(', ', $fieldNames).") VALUES (".implode(', ',array_keys($values)).")");
        $stmt->execute($values);

        return $this->pdo->lastInsertId();
	}

	public function updateSharedFile($owner, $storage, $path, $principalUri, $access)
	{
		$stmt = $this->pdo->prepare('UPDATE ' . $this->sharedFilesTableName . ' SET access = ? WHERE owner = ? AND principaluri = ? AND storage = ? AND path = ?');
		return  $stmt->execute([$access, $owner, $principalUri, $storage, $path]);
	}

	public function updateSharedFileName($principalUri, $uid, $name, $share_path = '')
	{
		$stmt = $this->pdo->prepare('UPDATE ' . $this->sharedFilesTableName . ' SET `uid` = ? WHERE principaluri = ? AND uid = ? AND share_path = ?');
		return  $stmt->execute([$name, $principalUri, $uid, $share_path]);
	}

	public function updateSharedFileSharePath($principalUri, $uid, $sharePath, $newSharePath)
	{
		$stmt = $this->pdo->prepare('UPDATE ' . $this->sharedFilesTableName . ' SET `share_path` = ? WHERE principaluri = ? AND uid = ? AND share_path = ?');
		return  $stmt->execute([$newSharePath, $principalUri, $uid, $sharePath]);
	}

	public function updateSharedFileSharePathWithLike($principalUri, $sharePath, $newSharePath)
	{
		$stmt = $this->pdo->prepare(
			'UPDATE ' . $this->sharedFilesTableName . '
			SET share_path = REPLACE(share_path, ?, ?)
			WHERE principaluri = ? AND share_path LIKE ?'
		);
		return  $stmt->execute([$sharePath, $newSharePath, $principalUri, $sharePath . '%']);
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
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->sharedFilesTableName.' WHERE owner = ? AND storage = ? AND path = ?');
        return $stmt->execute([$owner, $storage, $path]);
	}

		/**
	 *
	 * @param string $principaluri
	 * @param string $storage
	 * @param string $path
	 * @return bool
	 */
	public function deleteSharedFileByPrincipalUri($principaluri, $storage, $path)
	{
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->sharedFilesTableName.' WHERE principaluri = ? AND storage = ? AND path = ?');
        return $stmt->execute([$principaluri, $storage, $path]);
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
}
