<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class File extends Afterlogic\DAV\FS\Shared\File{
	
	protected $linkPath;

	protected $sharedItem;
    
	protected $isLink;

	public function __construct($path, $sharedItem, $isLink = false) {

		parent::__construct($sharedItem->getPath());

		$this->sharedItem = $sharedItem;
		$this->linkPath = $path;
		$this->isLink = $isLink;
		
    }
	
	public function getRootPath($sType = \Aurora\System\Enums\FileStorageType::Personal) {

		return $this->path;

    }

	public function getPath() {

		return $this->linkPath;

    }
	
	public function getName() {

        if ($this->isLink) {
			return $this->sharedItem->getName();
		} else {
	        list(, $name)  = \Sabre\HTTP\URLUtil::splitPath($this->linkPath);
		    return $name;
		}

    }

	public function getOwner() {

        return $this->sharedItem->getOwner();

    }

	public function getAccess() {

        return $this->sharedItem->getAccess();

    }

	public function getLink() {

        return $this->sharedItem->getLink();

    }

	public function isDirectory() {

        return $this->sharedItem->isDirectory();

    }

	public function getDirectory() {
		
		return new Directory(dirname($this->path));
		
	}
	
    /**
     * Returns the data
     *
     * @return resource
     */
    public function get() {

		return fopen($this->path,'r');

    }	
	
	public function delete() {

        parent::delete();
		
		$this->getDirectory()->updateQuota();

    }
}

