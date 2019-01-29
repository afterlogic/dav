<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class Root extends \Afterlogic\DAV\FS\Personal\Root{
	
    protected $pdo = null;
	
	public function __construct($path, $sUserPublicId = null) {
		
		if (empty($sUserPublicId))
		{
			$sUserPublicId = $this->getUser();
		}
		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
		
		if ($oUser) {
			
			$path = $path . '/' . $oUser->UUID;
		}
		
		$this->path = $path;

		$this->pdo = new \Afterlogic\DAV\FS\Backend\PDO();
	}

	
	public function getName() {

        return \Aurora\System\Enums\FileStorageType::Shared;

	}	
	
	protected function populateItem($aSharedFile)
	{
		$mResult = false;

		if (is_array($aSharedFile))
		{
			$sRootPath = \Aurora\System\Api::DataPath() . '/' . \Afterlogic\DAV\FS\Plugin::getPathByStorage(
				basename($aSharedFile['owner']), 
				$aSharedFile['storage']
			);
			
			$path = $sRootPath . '/' . trim($aSharedFile['path'], '/');
					
			if ($aSharedFile['isdir'])
			{
				$mResult = new Directory(
					$aSharedFile['owner'],
					$aSharedFile['principaluri'],
					$aSharedFile['storage'],
					$path,
					$aSharedFile['access'],
					$aSharedFile['uid']
				);
			}
			else
			{
				$mResult = new File(
					$aSharedFile['owner'],
					$aSharedFile['principaluri'],
					$aSharedFile['storage'],
					$path,
					$aSharedFile['access'],
					$aSharedFile['uid']
				);
			}
		}
		return $mResult;		
	}
	
    public function getChild($name) {

		$aSharedFile = $this->pdo->getSharedFileByUid('principals/' . $this->UserPublicId, $name);

		return $this->populateItem($aSharedFile);
		
    }	
	
	public function getChildren() {

		$aResult = [];
		
		$aSharedFiles = $this->pdo->getSharedFilesForUser('principals/' . $this->UserPublicId);

		foreach ($aSharedFiles as $aSharedFile)
		{
			$oSharedItem = $this->populateItem($aSharedFile);
			if ($oSharedItem)	
			{
				$aResult[] = $oSharedItem;
			}
		}
		
		return $aResult;

    }	
	
    function getLastModified() {
		return time();
	}	
	
    public function getQuotaInfo() {

    }		
	
}
