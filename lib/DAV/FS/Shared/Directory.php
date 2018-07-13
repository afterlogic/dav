<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class Directory extends Afterlogic\DAV\FS\Directory {
    
	protected $linkPath;
	
	protected $sharedNode;

	protected $isLink;

	/**
     * Constructor
     *
     * @param string $path
     */
    public function __construct($path, $sharedNode, $isLink = false) {

		parent::__construct($sharedNode->getPath());

		$this->linkPath = $path;
		$this->sharedNode = $sharedNode;
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
			
			return $this->sharedNode->getName();
		} else {
	        list(, $name)  = \Sabre\HTTP\URLUtil::splitPath($this->linkPath);
		    return $name;
		}

    }

	public function createDirectory($name) {

		throw new DAV\Exception\Forbidden('Permission denied');
		
    }

	public function createFile($name, $data = null) {

		throw new DAV\Exception\Forbidden('Permission denied');

    }

    public function getChild($name) {

        $path = $this->path . '/' . trim($name, '/');

        if (!file_exists($path)) {
			throw new \Sabre\DAV\Exception\NotFound(
					'File with name ' . $path . ' could not be located'
			);
		}

        if (is_dir($path)) {

            return new Directory($path, $this->sharedNode);

        } else {

            return new File($path, $this->sharedNode);

        }

    }	
	
	public function getChildren() {

		$nodes = array();
		
		if(!file_exists($this->path)) {
			mkdir($this->path);
		}
		
        foreach(scandir($this->path) as $node) {
			if($node!='.' && $node!='..' && $node!== '.sabredav' && 
					$node!== AU_API_HELPDESK_PUBLIC_NAME) {
				$nodes[] = $this->getChild($node);
			}
		}
        return $nodes;

    }
	
    public function childExists($name) {

		return parent::childExists($name);

    }

    public function delete() {

		parent::delete();
		
		$this->updateQuota();
    }	
	
	public function Search($pattern, $path = null) {

		$aResult = array();
		if ($path === null)	{
			
			$path = $this->path;
		}
		$aItems = \Aurora\System\Utils::SearchFiles($path, $pattern);
		if ($aItems) {
			
			foreach ($aItems as $sItem) {
				if (is_dir($sItem)) {
					$aResult[] = new Directory($sItem);
				} else {
					$aResult[] = new File($sItem);
				}
			}
		}
		
		return $aResult;
	}
}