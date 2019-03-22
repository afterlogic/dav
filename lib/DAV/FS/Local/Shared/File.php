<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class File extends \Afterlogic\DAV\FS\File implements \Sabre\DAVACL\IACL 
{
    use NodeTrait;    
	
	protected $owner;
    protected $principalUri;
	protected $access;
	protected $uid;

    public function __construct($owner, $principalUri, $storage, $path, $access, $uid = null, $inRoot = false) 
    {
        $this->owner = $owner;
        $this->principalUri = $principalUri;
        $this->access = $access;
        $this->uid = $uid;
        $this->inRoot = $inRoot;
        
        parent::__construct($storage, $path);
    }

    public function getOwner() 
    {
        return $this->principalUri;
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
        return $this->getName();

//        list(, $name) = \Sabre\Uri\split($this->getPath());
//        return $name;
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
        if (!$this->inRoot)
        {
            parent::setName($name);
        }
        else
        {
            throw new \Sabre\DAV\Exception\Conflict();            
        }
    }

}

