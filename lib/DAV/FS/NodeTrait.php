<?php

namespace Afterlogic\DAV\FS;

trait NodeTrait
{
    public function getRootPath()
	{
		$sPath = null;

		$oServer = \Afterlogic\DAV\Server::getInstance();
        list(, $owner) = \Sabre\Uri\split($this->getOwner());
		$oServer->setUser($owner);
		$oNode = $oServer->tree->getNodeForPath('files/'. $this->getStorage());
		if ($oNode)
		{
			$sPath = $oNode->getPath();
		}
		
		return $sPath;
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
		if ($oSharedFilesModule && !$oSharedFilesModule->Disabled)
		{
			$sRelativePath =  $this->getRelativePath();
			$sPath = (!empty($sRelativePath) ? $sRelativePath . '/' : '') . $this->getName();

			$pdo = new \Afterlogic\DAV\FS\Backend\PDO();
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

}