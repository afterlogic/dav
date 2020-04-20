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
	 * @var string
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
    public function getSharedFilesForUser($principalUri) {

		$aResult = [];

		$fields[] = 'id';
        $fields[] = 'owner';
        $fields[] = 'principaluri';
        $fields[] = 'storage';
        $fields[] = 'path';
        $fields[] = 'uid';
        $fields[] = 'access';
        $fields[] = 'isdir';

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.principaluri = ?
SQL
        );

		$stmt->execute([$principalUri]);
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

        // Making fields a comma-delimited list
        $fields = implode(', ', $fields);
        $stmt = $this->pdo->prepare(<<<SQL
SELECT $fields FROM {$this->sharedFilesTableName}
WHERE {$this->sharedFilesTableName}.principaluri = ? AND uid = ?
SQL
        );

		$stmt->execute([$principalUri, $uid]);
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
				'principaluri' => $row['principaluri'],
				'access' => (int) $row['access'],
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

	public function updateSharedFileName($principalUri, $uid, $name)
	{
		$stmt = $this->pdo->prepare('UPDATE ' . $this->sharedFilesTableName . ' SET `uid` = ? WHERE principaluri = ? AND uid = ?');
		return  $stmt->execute([$name, $principalUri, $uid]);
	}

	public function updateShare($owner, $storage, $path, $newPath)
	{
		$stmt = $this->pdo->prepare(
			"UPDATE " . $this->sharedFilesTableName . "
			SET path = REPLACE(path, ?, ?)
			WHERE path LIKE ? AND owner = ? AND storage = ?"
		);
		return  $stmt->execute([$path, $newPath, $path . '%', $owner, $storage]);
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
	 * @param string $owner
	 * @param string $path
	 * @return bool
	 */
	public function deleteShare($principaluri, $uid)
	{
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->sharedFilesTableName.' WHERE principaluri = ? AND uid = ?');
        return $stmt->execute([$principaluri, $uid]);
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
