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
			$sClass = \implode(
				'\\',
				['Afterlogic', 'DAV', 'FS', \ucfirst($sStorage), 'Root']
			);

			$aChildren[] = new $sClass();
		}
			
		return $aChildren;
	}
}