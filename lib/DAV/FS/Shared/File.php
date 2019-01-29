<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class File extends \Afterlogic\DAV\FS\File{
	
	protected $owner;
    protected $principalUri;
    protected $storage;
	protected $access;
	protected $uid;

    public function __construct($owner, $principalUri, $storage, $path, $access, $uid = null) {

        $this->owner = $owner;
        $this->principalUri = $principalUri;
        $this->storage = $storage;
        $this->access = $access;
        $this->uid = $uid;
        
        parent::__construct($path);
        
    }
	
	public function getOwner() {

        return $this->owner;

    }
    
    public function getStorage() {

        return $this->storage;

    }

	public function getAccess() {

        return $this->access;

    }

    public function getName() {

        list(, $name) = \Sabre\Uri\split($this->path);
        return isset($this->uid) ? $this->uid : $name;

    }	

    public function getId()
    {
        return $this->getName();
    }

    public function getDisplayName()
	{
        list(, $name) = \Sabre\Uri\split($this->path);
        return $name;
	}

	public function getRelativePath() 
	{

        list(, $owner) = \Sabre\Uri\split($this->owner);
        list($dir,) = \Sabre\Uri\split($this->getPath());

//        var_dump($owner); exit;

		return \str_replace(
            \Aurora\System\Api::DataPath() . '/' . \Afterlogic\DAV\FS\Plugin::getPathByStorage(
                $owner, 
                $this->getStorage()
            ), 
            '', 
            $dir
        );

    }    

    /**
     * Updates the data
     *
     * Data is a readable stream resource.
     *
     * @param resource|string $data
     * @return string
     */
    function put($data) {

        if ($this->access === 1)
        {
            return parent::put($data);
        }
        else
        {
            throw new \Sabre\DAV\Exception\Forbidden();
        }
    }

    /**
     * Updates the file based on a range specification.
     *
     * The first argument is the data, which is either a readable stream
     * resource or a string.
     *
     * The second argument is the type of update we're doing.
     * This is either:
     * * 1. append
     * * 2. update based on a start byte
     * * 3. update based on an end byte
     *;
     * The third argument is the start or end byte.
     *
     * After a successful put operation, you may choose to return an ETag. The
     * ETAG must always be surrounded by double-quotes. These quotes must
     * appear in the actual string you're returning.
     *
     * Clients may use the ETag from a PUT request to later on make sure that
     * when they update the file, the contents haven't changed in the mean
     * time.
     *
     * @param resource|string $data
     * @param int $rangeType
     * @param int $offset
     * @return string|null
     */
    function patch($data, $rangeType, $offset = null) {

        if ($this->access === 1)
        {
            return parent::patch($data, $rangeType, $offset);
        }
        else
        {
            throw new \Sabre\DAV\Exception\Forbidden();
        }

    }

    /**
     * Delete the current file
     *
     * @return bool
     */
    function delete() {

        if ($this->access === 1)
        {
            return parent::delete();
        }
        else
        {
            throw new \Sabre\DAV\Exception\Forbidden();
        }

    }    

    public function setName($name) {

        if ($this->access === 1)
        {
            parent::setName($name);
        }
        else
        {
            throw new \Sabre\DAV\Exception\Forbidden();
        }

    }	

}

