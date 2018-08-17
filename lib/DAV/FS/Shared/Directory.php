<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class Directory extends \Sabre\DAV\FSExt\Directory {
    
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

		var_dump($this->path);
		
        if (!file_exists($path)) throw new \Sabre\DAV\Exception\NotFound('File could not be located');

		$mResult = null;
		if (is_dir($path))
		{
			$mResult = new \Afterlogic\DAV\FS\Directory(basename($path));
		}
		else
		{
			$mResult = new \Afterlogic\DAV\FS\File(basename($path));
		}
		if (isset($mResult))
		{
			$mResult->setPath(dirname($path));
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

            $nodes[] = $this->getChild($entry->getPathname());

        }
        return $nodes;

    }	
	
}