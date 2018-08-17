<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Backend;

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
		$this->dBPrefix = \Aurora\System\Api::GetSettings()->GetConf('DBPrefix');
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
        $fields[] = 'path';
        $fields[] = 'uid';
        $fields[] = 'access';

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
				'path' => $row['path'],
				'access' => $row['access'],
			];
		}
		
		return $aResult;
	}
	
    /* @param string $principalUri
     * @return array
     */
    public function getSharedFile($principalUri, $uid) {

		$aResult = false;
		
		$fields[] = 'id';
        $fields[] = 'owner';
        $fields[] = 'principaluri';
        $fields[] = 'path';
        $fields[] = 'uid';
        $fields[] = 'access';

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
				'path' => $row['path'],
				'access' => $row['access'],
			];
		}
		
		return $aResult;
	}	
	
	/**
	 * 
	 * @param type $owner
	 * @param type $principalUri
	 * @param type $path
	 * @param type $access
	 * @return type
	 */
	public function createSharedFile($owner, $principalUri, $path, $access)
	{
		$values = $fieldNames = [];
        $fieldNames[] = 'owner';
		$values[':owner'] = $owner;

		$fieldNames[] = 'principaluri';
		$values[':principaluri'] = $principalUri;

		$fieldNames[] = 'path';
		$values[':path'] = $path;

		$fieldNames[] = 'access';
		$values[':access'] = (int) $access;

		$stmt = $this->pdo->prepare("INSERT INTO ".$this->sharedFilesTableName." (".implode(', ', $fieldNames).") VALUES (".implode(', ',array_keys($values)).")");
        $stmt->execute($values);

        return $this->pdo->lastInsertId();			
	}
	
	/**
	 * 
	 * @param type $owner
	 * @param type $principalUri
	 * @param type $path
	 */
	public function deleteSharedFile($owner, $principalUri, $path)
	{
        $stmt = $this->pdo->prepare('DELETE FROM '.$this->sharedFilesTableName.' WHERE owner = ? AND principaluri = ? AND path = ?');
        $stmt->execute([$owner, $principalUri, $path]);		
	}
	
}
