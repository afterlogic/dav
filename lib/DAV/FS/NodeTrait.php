<?php

namespace Afterlogic\DAV\FS;

use Afterlogic\DAV\Server;

trait NodeTrait
{
	/**
	 *
	 * @var [string]
	 */
	protected $rootPath = null;

	/**
	 * @var string $storage
	 */
	protected $storage = null;

	/**
	 * @var string $UserPublicId
	 */
    protected $UserPublicId = null;		

	
	public function getStorage() 
	{
    	return $this->storage;
	}
	
	public function getRootPath()
	{
		if ($this->rootPath === null)
		{
			list(, $owner) = \Sabre\Uri\split($this->getOwner());
			Server::getInstance()->setUser($owner);
			$oNode = Server::getInstance()->tree->getNodeForPath('files/'. $this->getStorage());

			if ($oNode)
			{
				$this->rootPath = $oNode->getPath();
			}
		}
		return $this->rootPath;
    }  
        
	public function getRelativePath() 
	{
        list($dir) = \Sabre\Uri\split($this->getPath());

		return \str_replace(
            $this->getRootPath(), 
            '', 
            $dir
        );
    }

   public function deleteShares()
	{
		$oSharedFilesModule = \Aurora\System\Api::GetModule('SharedFiles');
		if ($oSharedFilesModule && !$oSharedFilesModule->getConfig('Disabled'))
		{
			$sRelativePath =  $this->getRelativePath();
			$sPath = (!empty($sRelativePath) ? $sRelativePath . '/' : '') . $this->getName();

			$pdo = new Backend\PDO();
			$pdo->deleteSharedFile($this->getOwner(), $this->getStorage(), $sPath);
		}
	}     
	
	public function checkFileName($name)
	{
		if (strlen(trim($name)) === 0) throw new \Sabre\DAV\Exception\Forbidden('Permission denied to emty item');

        $path = $this->path . '/' . trim($name, '/');

        if (!file_exists($path)) throw new \Sabre\DAV\Exception\NotFound('File could not be located');
        if ($name == '.' || $name == '..') throw new \Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');
		
		return $path;
	}

    public function getDisplayName()
	{
		return $this->getName();
	}
    
    public function getId()
	{
		return $this->getName();
    }
    
    public function getPath() 
    {
        return $this->path;
    }

	public function setPath($path)
	{
		$this->path = $path;
	}	
	
    public function getOwner()
	{
        if ($this->UserPublicId === null) 
        {
			$this->UserPublicId = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . \Afterlogic\DAV\Server::getUser();
		}
		return $this->UserPublicId;
	}	

	public function getUser()
	{
		return $this->getOwner();
	}

}