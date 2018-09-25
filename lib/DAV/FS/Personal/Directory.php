<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Personal;

class Directory extends \Afterlogic\DAV\FS\Directory {

	public function getChild($name) {

		if (strlen(trim($name)) === 0) throw new \Sabre\DAV\Exception\Forbidden('Permission denied to empty item');
		
		$path = $this->path . '/' . trim($name, '/');

		if (!file_exists($path)) throw new \Sabre\DAV\Exception\NotFound('File could not be located');
		if ($name == '.' || $name == '..') throw new \Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');

		return is_dir($path) ? new self($path) : new File($path);
	}	 
	
	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = array()) {
		$result = parent::createFile($name, $data, $rangeType, $offset, $extendedProps);

		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		if ($oModuleManager->IsAllowedModule('PersonalFiles')) {
			\Aurora\Modules\PersonalFiles\Module::Decorator()->UpdateUsedSpace();
		}

		return $result;
	}
	
	function getQuotaInfo() {
		
	}	
}