<?php

namespace Afterlogic\DAV\FS;

use Afterlogic\DAV\Constants;

class FilesRoot extends \Sabre\DAV\Collection {

	public function getName() {
		
		return 'files';
		
	}
	
	public function getChildren() {
		
		$aTree = [];
		
		$bErrorCreateDir = false;

		$sRootDir = \Aurora\System\Api::DataPath() . Constants::FILESTORAGE_PATH_ROOT;

		$aPaths = [
			['root', $sRootDir]
		];

		$oPersonalFiles = \Aurora\System\Api::GetModule('PersonalFiles'); 
		if ($oPersonalFiles && !$oPersonalFiles->getConfig('Disabled', false)) {
			$aPaths[] = [
				\Aurora\System\Enums\FileStorageType::Personal, 
				$sRootDir . Constants::FILESTORAGE_PATH_PERSONAL
			];
		}

		$oCorpFiles = \Aurora\System\Api::GetModule('CorporateFiles'); 
		if ($oCorpFiles && !$oCorpFiles->getConfig('Disabled', false)) {
			$aPaths[] = [
				\Aurora\System\Enums\FileStorageType::Corporate, 
				$sRootDir . Constants::FILESTORAGE_PATH_CORPORATE
			];
		}

		$oDavModule = \Aurora\System\Api::GetModule('Dav'); 
		if ($oDavModule && $oDavModule->getConfig('FilesSharing', true)) {
			$aPaths[] = [
				\Aurora\System\Enums\FileStorageType::Shared, 
				$sRootDir . Constants::FILESTORAGE_PATH_SHARED
			];
		}
		 
		foreach ($aPaths as $aPath)
		{
			$sType = $aPath[0];
			$sPath = $aPath[1];
			
			if (!file_exists($sPath) && $sType !== \Aurora\System\Enums\FileStorageType::Shared) {
				if (!@mkdir($sPath)) {
					throw new \Sabre\DAV\Exception(
							'Can\'t create directory in ' . $sRootDir, 
							500
					);
				}
			}

			switch ($sType)
			{
				case \Aurora\System\Enums\FileStorageType::Personal:
					$aTree[] = new Personal\Root($sPath);
					break;
				case \Aurora\System\Enums\FileStorageType::Corporate:
					$aTree[] = new Corporate\Root($sPath);
					break;
				case \Aurora\System\Enums\FileStorageType::Shared:
					$aTree[] = new Shared\Root($sPath);
					break;
			}
		}		
		
		return $aTree;
	}
	
}