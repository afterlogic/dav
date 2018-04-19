<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS;

class RootCorporate extends Directory {
	
	private $rootPath = null;

    public function initPath() {
		
		if ($this->rootPath === null) {
			
			$oTenant = $this->getTenant();
			if ($oTenant) {
				
				$this->rootPath = $this->path . '/' . $oTenant->EntityId;
				if (!\file_exists($this->rootPath)) {
					
					\mkdir($this->rootPath, 0777, true);
				}
			}
		}
		if ($this->rootPath !== null) {
			
			$this->path = $this->rootPath;
		}
	}	
	
    public function getName() {

        return 'corporate';

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
