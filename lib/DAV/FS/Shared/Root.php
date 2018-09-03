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
	
    public function getChild($name) {

		$mResult = false;
		$aSharedFile = $this->pdo->getSharedFile('principals/' . $this->UserPublicId, $name);
		
		if (is_array($aSharedFile))
		{
			if (is_dir($aSharedFile['path']))
			{
				$mResult = new Directory(
					$aSharedFile['owner'],
					$aSharedFile['principaluri'],
					$aSharedFile['path'],
					$aSharedFile['uid'],
					$aSharedFile['access']
				);
			}
			else
			{
				$mResult = new File(
					$aSharedFile['owner'],
					$aSharedFile['principaluri'],
					$aSharedFile['path'],
					$aSharedFile['uid'],
					$aSharedFile['access']
				);
			}
		
		}
		
		return $mResult;
		
    }	
	
	public function getChildren() {

		$aResult = [];
		
		$aSharedFiles = $this->pdo->getSharedFilesForUser('principals/' . $this->UserPublicId);
		
		foreach ($aSharedFiles as $aSharedFile)
		{
			if (is_dir($aSharedFile['path']))
			{
				$aResult[] = new Directory(
					$aSharedFile['owner'],
					$aSharedFile['principaluri'],
					$aSharedFile['path'],
					$aSharedFile['uid'],
					$aSharedFile['access']
				);
			}
			else
			{
				$aResult[] = new File(
					$aSharedFile['owner'],
					$aSharedFile['principaluri'],
					$aSharedFile['path'],
					$aSharedFile['uid'],
					$aSharedFile['access']
				);
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
