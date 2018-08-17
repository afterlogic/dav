<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS;

class RootShared extends RootPersonal{
	
    protected $pdo = null;
	
	public function __construct($path, $sUserPublicId = null) {
		parent::__construct($path, $sUserPublicId);
		$this->pdo = new \Afterlogic\DAV\FS\Backend\PDO();
	}

		public function getName() {

        return 'shared';

    }	
	
    public function getChild($name) {

		$mResult = false;
		$aSharedFile = $this->pdo->getSharedFile('principals/' . $this->UserPublicId, $name);
		
		if (is_array($aSharedFile))
		{
			if (is_dir($aSharedFile['path']))
			{
				$mResult = new \Afterlogic\DAV\FS\Shared\Directory(
					$aSharedFile['owner'],
					$aSharedFile['principaluri'],
					$aSharedFile['path'],
					$aSharedFile['uid'],
					$aSharedFile['access']
				);
			}
			else
			{
				$mResult = new \Afterlogic\DAV\FS\Shared\File(
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
				$aResult[] = new \Afterlogic\DAV\FS\Shared\Directory(
					$aSharedFile['owner'],
					$aSharedFile['principaluri'],
					$aSharedFile['path'],
					$aSharedFile['uid'],
					$aSharedFile['access']
				);
			}
			else
			{
				$aResult[] = new \Afterlogic\DAV\FS\Shared\File(
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
	
}
