<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS;

class RootShared extends RootPersonal{
	
    public function getName() {

        return 'shared';

    }	
	
    public function getChild($name) {

		$this->initPath();
		
        $path = $this->path . '/' . trim($name, '/');

        if (!file_exists($path)) {

			throw new \Sabre\DAV\Exception\NotFound('File with name ' . $path . ' could not be located');
			
		}

		if (!is_dir($path)) {
			
			$node = new Shared\Node($this->authPlugin, $path);
			
			if (!$node->exists()) {
				
				$node->delete();
			}
/*
			$item->updateProperties(array(
				'owner' => 'test1@localhost',
				'access' => \ECalendarPermission::Write,
				'link' => 'folder',
				'directory' => true
			));
*/		
			return $node->getItem();
		} else {
			
			return false;
		}

    }	
	
	public function getChildren() {

		$this->initPath();
		
		if(!file_exists($this->path)) {
			
			mkdir($this->path);
		}
		
		$nodes = array();
        foreach(scandir($this->path) as $node)  {
			
			if($node!=='.' && $node!=='..' && $node!== '.sabredav' && $node !== AU_API_HELPDESK_PUBLIC_NAME) {

				$child = $this->getChild($node);
				if ($child) {
					
					$nodes[] = $child;
				}
			}
		}
        return $nodes;

    }	
	
}
