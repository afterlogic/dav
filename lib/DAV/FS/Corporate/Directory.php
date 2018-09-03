<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Corporate;

class Directory extends \Afterlogic\DAV\FS\Directory {
    
    public function getChild($name) {

		if (empty(trim($name))) throw new \Sabre\DAV\Exception\Forbidden('Permission denied to emty item');
		
        $path = $this->path . '/' . trim($name, '/');

        if (!file_exists($path)) throw new \Sabre\DAV\Exception\NotFound('File could not be located');
        if ($name == '.' || $name == '..') throw new \Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');

		return is_dir($path) ? new self($path) : new File($path);
    }		
	
    function getQuotaInfo() {
		
	}	
	
}