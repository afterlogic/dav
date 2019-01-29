<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Personal;

class File extends \Afterlogic\DAV\FS\File{

    public function getStorage() {

        return \Aurora\System\Enums\FileStorageType::Personal;

    }

	public function delete() {
		$result = parent::delete();
		
		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		if ($oModuleManager->IsAllowedModule('PersonalFiles')) {
			\Aurora\Modules\PersonalFiles\Module::Decorator()->UpdateUsedSpace();
		}
		
		return $result;
	}
	
	public function put($data) {
		$result = parent::put($data);

		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		if ($oModuleManager->IsAllowedModule('PersonalFiles')) {
			\Aurora\Modules\PersonalFiles\Module::Decorator()->UpdateUsedSpace();
		}

		return $result;
	}
}

