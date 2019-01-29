<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class Directory extends \Afterlogic\DAV\FS\Directory {
    
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

    public function getDisplayName()
	{
        list(, $name) = \Sabre\Uri\split($this->path);
        return $name;
	}

    public function getId()
    {
        return $this->getName();
    }

	public function getRelativePath() 
	{
        list(, $owner) = \Sabre\Uri\split($this->owner);
        list($dir,) = \Sabre\Uri\split($this->getPath());

		return \str_replace(
            \Aurora\System\Api::DataPath() . '/' . \Afterlogic\DAV\FS\Plugin::getPathByStorage(
                $owner, 
                $this->getStorage()
            ), 
            '', 
            $dir
        );

    }    
	
    public function getChild($path) {


        $mResult = null;
        
        $path = $this->path . '/' . $path;

        if (!file_exists($path)) throw new \Sabre\DAV\Exception\NotFound('File could not be located');

		if (is_dir($path))
		{
            $mResult = new self($this->owner, $this->principalUri, $this->storage, $path, $this->access);
		}
		else
		{
            $mResult = new File($this->owner, $this->principalUri, $this->storage, $path, $this->access);
		}
		
		return $mResult;
    }	
	
	
    function getChildren() {

        $nodes = [];

        $iterator = new \FilesystemIterator(
            $this->path,
            \FilesystemIterator::CURRENT_AS_SELF
          | \FilesystemIterator::SKIP_DOTS
        );

        foreach ($iterator as $entry) {
	
            if ($entry->getFilename() !== '.sabredav')
            {
                $nodes[] = $this->getChild($entry->getFilename());
            }
        }
        return $nodes;

    }

    /**
     * Creates a new file in the directory
     *
     * Data will either be supplied as a stream resource, or in certain cases
     * as a string. Keep in mind that you may have to support either.
     *
     * After successful creation of the file, you may choose to return the ETag
     * of the new file here.
     *
     * The returned ETag must be surrounded by double-quotes (The quotes should
     * be part of the actual string).
     *
     * If you cannot accurately determine the ETag, you should not return it.
     * If you don't store the file exactly as-is (you're transforming it
     * somehow) you should also not return an ETag.
     *
     * This means that if a subsequent GET to this new file does not exactly
     * return the same contents of what was submitted here, you are strongly
     * recommended to omit the ETag.
     *
     * @param string $name Name of the file
     * @param resource|string $data Initial payload
     * @return null|string
     */
    function createFile($name, $data = null) {

        if ($this->access === 1)
        {
            parent::createFile($name, $data);
        }
        else
        {
            throw new \Sabre\DAV\Exception\Forbidden();
        }

    }

    /**
     * Creates a new subdirectory
     *
     * @param string $name
     * @return void
     */
    function createDirectory($name) {

        if ($this->access === 1)
        {
            parent::createDirectory($name);
        }
        else
        {
            throw new \Sabre\DAV\Exception\Forbidden();
        }
    }       

    public function delete() {

        if ($this->access === 1)
        {
            parent::delete();
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