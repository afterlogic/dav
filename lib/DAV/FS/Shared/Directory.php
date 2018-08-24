<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class Directory extends \Afterlogic\DAV\FS\Directory {
    
	protected $owner;
	protected $principalUri;
	protected $uid;
	protected $access;

    public function __construct($owner, $principalUri, $path, $uid, $access) {

		parent::__construct($path);
        $this->owner = $owner;
        $this->principalUri = $principalUri;
        $this->uid = $uid;
        $this->access = $access;
		
    }
	
	public function getOwner() {

        return $this->owner;

    }

	public function getAccess() {

        return $this->access;

    }

    public function getName() {

        return $this->uid;

    }	
	
    public function getChild($path) {

        if (!file_exists($this->path . '/' . $path)) throw new \Sabre\DAV\Exception\NotFound('File could not be located');

		$mResult = null;
		if (is_dir($this->path . '/' . $path))
		{
			$mResult = new \Afterlogic\DAV\FS\Shared\Directory($this->owner, $this->principalUri, $this->path . '/' . $path, $path, $this->access);
		}
		else
		{
			$mResult = new \Afterlogic\DAV\FS\Shared\File($this->owner, $this->principalUri, $this->path . '/' . $path, $path, $this->access);
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
	
            $oChild = $this->getChild($entry->getFilename());
			$nodes[] = $oChild;
        }
        return $nodes;

    }	
	
}