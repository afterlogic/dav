<?php

namespace Afterlogic\DAV\FS\Local;

trait NodeTrait 
{
	protected function updateUsedSpace()
	{
		$oModuleManager = \Aurora\System\Api::GetModuleManager();
		if ($oModuleManager->IsAllowedModule('PersonalFiles')) 
		{
			\Aurora\Modules\PersonalFiles\Module::Decorator()->UpdateUsedSpace();
		}
	}	

}