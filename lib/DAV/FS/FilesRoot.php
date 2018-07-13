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

		$aPaths = [['root', $sRootDir]];
		$aPaths[] = ['personal', $sRootDir . Constants::FILESTORAGE_PATH_PERSONAL];
		$oCorpFiles = \Aurora\System\Api::GetModule('CorporateFiles'); 
		if ($oCorpFiles && !$oCorpFiles->getConfig('Disabled', false)) {
			$aPaths[] = ['corporate', $sRootDir . Constants::FILESTORAGE_PATH_CORPORATE];
		}
		$oDavModule = \Aurora\System\Api::GetModule('Dav'); 
		if ($oDavModule && $oDavModule->getConfig('FilesSharing', true)) {
			$aPaths[] = ['shared', $sRootDir . Constants::FILESTORAGE_PATH_SHARED];
		}
		 
		foreach ($aPaths as $aPath)
		{
			$sType = $aPath[0];
			$sPath = $aPath[1];
			
			if (!file_exists($sPath)) {
				if (!@mkdir($sPath)) {
					$bErrorCreateDir = true;
				}
			}
			if ($bErrorCreateDir) {
				throw new \Sabre\DAV\Exception(
						'Can\'t create directory in ' . $sRootDir, 
						500
				);
			}
			switch ($sType)
			{
				case 'personal':
					$aTree[] = new RootPersonal($sPath);
					break;
				case 'corporate':
					$aTree[] = new RootCorporate($sPath);
					break;
				case 'shared':
					$aTree[] = new RootShared($sPath);
					break;
			}
		}		
		
		return $aTree;
	}
	
}