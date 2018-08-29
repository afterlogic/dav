<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Corporate;

class Root extends Directory {
	
	public function __construct($path) {
		
		$oTenant = $this->getTenant();
		if ($oTenant) {

			$path = $path . '/' . $oTenant->EntityId;
			if (!\file_exists($path)) {

				\mkdir($path, 0777, true);
			}
		}

		parent::__construct($path);
	}
	
    public function getName() {

        return \Aurora\System\Enums\FileStorageType::Corporate;

    }	

	public function setName($name) {

		throw new \Sabre\DAV\Exception\Forbidden();

	}

	public function delete() {

		throw new \Sabre\DAV\Exception\Forbidden();

	} 	
	
    public function getQuotaInfo() {

        $Size = 0;
		$aResult = \Aurora\System\Utils::GetDirectorySize($this->path);
		if ($aResult && $aResult['size']) {
			
			$Size = (int) $aResult['size'];
		}
		return array(
            $Size,
            disk_free_space($this->path)
        );	
	}
	
}
