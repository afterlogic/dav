<?php

namespace Afterlogic\DAV\FS;

class Root extends \Sabre\DAV\Collection {

	public function getName() 
	{
		return 'files';
	}

	public function getChildrenCount() 
	{
		$aStorages = \Aurora\Modules\Files\Module::Decorator()->GetSubModules();

		return count($aStorages);
	}

	public function getChildren() 
	{
		$aChildren = [];
		$aStorages = \Aurora\Modules\Files\Module::Decorator()->GetSubModules();

		foreach ($aStorages as $sStorage)
		{
			$aClassPath = ['Afterlogic', 'DAV', 'FS'];

			$aStoragePath = \explode('.', $sStorage);
			foreach ($aStoragePath as $sPathItem)
			{
				$aClassPath[] = \ucfirst($sPathItem);
			}
			$aClassPath[] = 'Root';

			$sClass = \implode(
				'\\',
				$aClassPath
			);

			$aChildren[] = new $sClass();
		}
			
		return $aChildren;
	}
}