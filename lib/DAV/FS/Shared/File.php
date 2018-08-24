<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class File extends \Afterlogic\DAV\FS\File{
	
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
}

