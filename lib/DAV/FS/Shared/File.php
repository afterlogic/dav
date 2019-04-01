<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class File extends \Afterlogic\DAV\FS\File implements \Sabre\DAVACL\IACL 
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

    // public function getOwner() 
    // {
    //     return $this->principalUri;
    // }

    public function getAccess() 
    {
        return $this->node->getAccess();
    }

    public function getName() 
    {
        return $this->node->getName();
    }	

    public function getId()
    {
        return $this->getName();
    }

    public function getDisplayName()
	{
        return $this->getName();
	}

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getLastModified() 
    {
        return $this->node->getLastModified();
    }    

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getSize() 
    {
        return $this->node->getSize();
    }        

    function get()
    {
        return $this->node->get();
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

    public function put($data)
    {
        return $this->node->put($data);
    }

}

