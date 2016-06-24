<?php

namespace Afterlogic\DAV\FS;

use Afterlogic\DAV\Constants;

class FilesRoot extends \Sabre\DAV\Collection {

	protected $aTree;
	
	public function __construct() 
	{
		$bErrorCreateDir = false;

		/* Public files folder */
		$publicDir = \CApi::DataPath() . Constants::FILESTORAGE_PATH_ROOT;
		if (!file_exists($publicDir)) {

			if (!@mkdir($publicDir)) {

				$bErrorCreateDir = true;
			}
		}

		$publicDir .= Constants::FILESTORAGE_PATH_CORPORATE;
		if (!file_exists($publicDir)) {

			if (!@mkdir($publicDir)) {

				$bErrorCreateDir = true;
			}
		}

		$personalDir = \CApi::DataPath() . Constants::FILESTORAGE_PATH_ROOT . 
				Constants::FILESTORAGE_PATH_PERSONAL;
		if (!file_exists($personalDir)) {

			if (!@mkdir($personalDir)) {

				$bErrorCreateDir = true;
			}
		}
		$sharedDir = \CApi::DataPath() . Constants::FILESTORAGE_PATH_ROOT . 
				Constants::FILESTORAGE_PATH_SHARED;
		if (!file_exists($sharedDir)) {

			if (!@mkdir($sharedDir)) {

				$bErrorCreateDir = true;
			}
		}

		if ($bErrorCreateDir) {

			throw new \Sabre\DAV\Exception(
					'Can\'t create directory in ' . \CApi::DataPath() . Constants::FILESTORAGE_PATH_ROOT, 
					500
			);
		}

		$this->aTree = array(
			new RootPersonal($personalDir)
		);
		
		$oApiCapaManager = \CApi::GetSystemManager('capability');
		if ($oApiCapaManager->isCollaborationSupported()) {

			array_push($this->aTree, new RootPublic($publicDir));
		}
		if (\CApi::GetConf('labs.files-sharing', false)) {

			array_push($this->aTree, new RootShared($sharedDir));
		}		
	}	
	
	
	public function getName() {
		
		return 'files';
		
	}
	
	public function getChildren() {
		
		return $this->aTree;
		
	}
	
}