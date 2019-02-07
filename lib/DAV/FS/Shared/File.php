<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class File extends \Afterlogic\DAV\FS\File implements \Sabre\DAVACL\IACL 
{
    use AclTrait;    
	
	protected $owner;
    protected $principalUri;
	protected $access;
	protected $uid;
	protected $inRoot;

    public function __construct($owner, $principalUri, $storage, $path, $access, $uid = null, $inRoot = false) 
    {
        $this->owner = $owner;
        $this->principalUri = $principalUri;
        $this->storage = $storage;
        $this->access = $access;
        $this->uid = $uid;
        $this->inRoot = $inRoot;
        
        parent::__construct($path);
    }

    public function getOwner() 
    {
        return $this->principalUri;
    }
    
    public function getStorage() 
    {
        return $this->storage;
    }

    public function getAccess() 
    {
        return $this->access;
    }

    public function getName() 
    {
        list(, $name) = \Sabre\Uri\split($this->getPath());
        return isset($this->uid) ? $this->uid : $name;
    }	

    public function getId()
    {
        return $this->getName();
    }

    public function getDisplayName()
	{
        list(, $name) = \Sabre\Uri\split($this->getPath());
        return $name;
	}

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getLastModified() 
    {
        if (\file_exists($this->path))
        {
            return \filemtime($this->path);
        }
        else
        {
            return null;
        }
    }    

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getSize() 
    {
        if (\file_exists($this->path))
        {
            return \filesize($this->path);
        }
        else
        {
            return null;
        }
    }        

    function delete()
    {
        if ($this->inRoot)
        {
            $pdo = new \Afterlogic\DAV\FS\Backend\PDO();

            $pdo->deleteShare($this->principalUri, $this->getId());
        }
        else
        {
            parent::delete();
        }
    }

    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name) 
    {
        if ($this->inRoot)
        {
            $pdo = new \Afterlogic\DAV\FS\Backend\PDO();
            var_dump($name); exit;
        }
        else
        {
            parent::setName($name);
        }
    }

}

