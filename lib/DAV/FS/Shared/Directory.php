<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class Directory extends \Afterlogic\DAV\FS\Directory implements \Sabre\DAVACL\IACL 
{
    use NodeTrait;
    
    protected $owner;
    protected $principalUri;
	protected $access;
	protected $uid;
	protected $inRoot;

    public function __construct($owner, $principalUri, $storage, $path, $access, $uid = null, $inRoot = false) 
    {
        $this->owner = $owner;
        $this->principalUri = $principalUri;
        $this->access = $access;
        $this->uid = $uid;
        $this->inRoot = $inRoot;

        parent::__construct($storage, $path);
    }

    public function getAccess() 
    {
        return $this->access;
    }

    public function getName() 
    {
        list(, $name) = \Sabre\Uri\split($this->path);
        return isset($this->uid) ? $this->uid : $name;
    }	

    public function getDisplayName()
	{
        return $this->getName();

//        list(, $name) = \Sabre\Uri\split($this->path);
//        return $name;
	}

    public function getId()
    {
        return $this->getName();
    }

    public function getChild($path) 
    {
        $mResult = null;
        
        $path = $this->path . '/' . $path;

        if (!\file_exists($path)) throw new \Sabre\DAV\Exception\NotFound('File could not be located');

		if (\is_dir($path))
		{
            $mResult = new self($this->owner, $this->principalUri, $this->storage, $path, $this->access);
		}
		else
		{
            $mResult = new File($this->owner, $this->principalUri, $this->storage, $path, $this->access);
		}
		
        return $mResult;
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
            $pdo->updateSharedFileName($this->principalUri, $this->getId(), $name);      
        }
        else
        {
            parent::setName($name);
        }
    }    
}