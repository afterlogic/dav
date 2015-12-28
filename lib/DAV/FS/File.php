<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS;

class File extends \Sabre\DAV\FSExt\File{
	
	public function getPath() {

        return $this->path;

    }
	
	public function getDirectory() {
		
		return new Directory(dirname($this->path));
		
	}
	
	public function delete() {

        parent::delete();
		
		$this->getDirectory()->updateQuota();

    }
	
	public function getProperty($sName)
	{
		$aData = $this->getResourceData();
		return isset($aData[$sName]) ? $aData[$sName] : null;
	}
	
	public function setProperty($sName, $mValue)
	{
		$aData = $this->getResourceData();
		$aData[$sName] = $mValue;
		$this->putResourceData($aData);
	}	
}

