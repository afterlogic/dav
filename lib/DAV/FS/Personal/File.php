<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Personal;

class File extends \Afterlogic\DAV\FS\File{

	public function delete() {
		$result = parent::delete();
		
		\Aurora\Modules\PersonalFiles\Module::Decorator()->UpdateUsedSpace();
		
		return $result;
	}
	
	public function put($data) {
		$result = parent::put($data);

		\Aurora\Modules\PersonalFiles\Module::Decorator()->UpdateUsedSpace();

		return $result;
	}
}

