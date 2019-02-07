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
        list(, $owner) = \Sabre\Uri\split($this->getOwner());
        list($dir,) = \Sabre\Uri\split($this->getPath());

		return \str_replace(
            $this->getRootPath(), 
            '', 
            $dir
        );
    }

   public function deleteShares()
	{
        $sRelativePath =  $this->getRelativePath();
        $sPath = (!empty($sRelativePath) ? $sRelativePath . '/' : '') . $this->getName();

        $pdo = new \Afterlogic\DAV\FS\Backend\PDO();
		$pdo->deleteSharedFile($this->getOwner(), $this->getStorage(), $sPath);
	}      

}