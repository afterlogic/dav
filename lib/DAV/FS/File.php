<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS;

class File extends \Sabre\DAV\FSExt\File implements \Sabre\DAVACL\IACL 
{
    use NodeTrait;
    use PropertyStorageTrait;
    
	public function __construct($storage, $path)
	{
		$this->storage = $storage;
		parent::__construct($path);
    }
    
    public function get($bRedirectToUrl = false)
    {
        return parent::get();
    }
    
    public function getDirectory() 
    {
        list($dir) = \Sabre\Uri\split($this->path);
		return new Directory($dir);
    }
	
    public function delete() 
    {
        $result = parent::delete();

        $this->deleteShares();

		$this->deleteResourceData();
		
		return $result;
    }
	
    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name) 
    {
        list($parentPath, $oldName) = \Sabre\Uri\split($this->path);
        list(, $newName) = \Sabre\Uri\split($name);
        $newPath = $parentPath . '/' . $newName;
        
		$sRelativePath = $this->getRelativePath();

		$oldPathForShare = $sRelativePath . '/' .$oldName;
		$newPathForShare = $sRelativePath . '/' .$newName;

		$pdo = new Backend\PDO();
		$pdo->updateShare($this->getOwner(), $this->getStorage(), $oldPathForShare, $newPathForShare);

        // We're deleting the existing resourcedata, and recreating it
        // for the new path.
        $resourceData = $this->getResourceData();
        $this->deleteResourceData();

        rename($this->path, $newPath);
        $this->path = $newPath;
        $this->putResourceData($resourceData);
    }

}

