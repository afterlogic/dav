<?php

namespace Afterlogic\DAV\FS;

use Afterlogic\DAV\Constants;

class Root extends \Sabre\DAV\Collection {

	public function getName() {
		
		return 'files';
		
	}

	protected function checkPath($sPath)
	{
		if (!file_exists($sPath)) {
			if (!@mkdir($sPath)) {
				throw new \Sabre\DAV\Exception(
						'Can\'t create \'' . $sPath . '\' directory', 
						500
				);
			}
		}

	}

	public function getChildren() {
		
		$aChildren = [];

		$sRootDir = \Aurora\System\Api::DataPath() . Constants::FILESTORAGE_PATH_ROOT;

		$oPersonalFiles = \Aurora\System\Api::GetModule('PersonalFiles'); 
		if ($oPersonalFiles && !$oPersonalFiles->getConfig('Disabled', false)) 
		{
			$sPath = $sRootDir . Constants::FILESTORAGE_PATH_PERSONAL;
			$this->checkPath($sPath);
			$aChildren[] = new Personal\Root($sPath);
		}

		$oCorpFiles = \Aurora\System\Api::GetModule('CorporateFiles'); 
		if ($oCorpFiles && !$oCorpFiles->getConfig('Disabled', false)) 
		{
			$sPath = $sRootDir . Constants::FILESTORAGE_PATH_CORPORATE;
			$this->checkPath($sPath);
			$aChildren[] = new Corporate\Root($sPath);
		}

		$oSharedFiles = \Aurora\System\Api::GetModule('SharedFiles'); 
		if ($oSharedFiles && !$oSharedFiles->getConfig('Disabled', false)) 
		{
			$aChildren[] = new Shared\Root();
		}
			
		return $aChildren;

	}
	
}