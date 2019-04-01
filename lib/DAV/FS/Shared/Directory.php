<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class Directory extends \Afterlogic\DAV\FS\Directory
{
    protected $node;

    public function __construct($node) 
    {
        $this->node = $node;
    }

    public function getPath()
    {
        return $this->node->getPath();
    }

    public function getName() 
    {
        return $this->node->getName();
    }	

    public function getDisplayName()
	{
        return $this->getName();
	}

    public function getId()
    {
        return $this->getName();
    }

    public function getChild($path) 
    {
        return $this->node->getChild($path);
    }

    public function getChildren()
    {
        return $this->node->getChildren();
    }

    function delete()
    {
        $pdo = new \Afterlogic\DAV\FS\Backend\PDO();
        $pdo->deleteShare($this->principalUri, $this->getId());
    }   
    
    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name) 
    {
        throw new \Sabre\DAV\Exception\Conflict();            
    }    

	public function createDirectory($name) 
	{
        $this->node->createDirectory($name);
    }

	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = []) 
	{
        return $this->node-> createFile($name, $data, $rangeType, $offset, $extendedProps);
    }
    
}