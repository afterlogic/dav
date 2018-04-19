<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS;

class RootPersonal extends Directory{
	
	private $rootPath = null;

	public function initPath() {
		
		$sUserPublicId = $this->getUser();
		$oCoreDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
		$oUser = $oCoreDecorator->GetUserByPublicId($sUserPublicId);
		
		if ($this->rootPath === null && $oUser) {
			
			$this->rootPath = $this->path . '/' . $oUser->UUID;
			if (!\file_exists($this->rootPath)) {
				
				\mkdir($this->rootPath, 0777, true);
			}
		}
		$this->path = $this->rootPath;
	}	

    public function getName() {

        return 'personal';

    }	
	
	public function setName($name) {

        throw new \Sabre\DAV\Exception\Forbidden();

    }

    public function delete() {

        throw new \Sabre\DAV\Exception\Forbidden();

    }
	
    public function getQuotaInfo() {

        $iSize = 0;
		$aResult = \Aurora\System\Utils::GetDirectorySize($this->path);
		if ($aResult && $aResult['size']) {
			
			$iSize = (int) $aResult['size'];
		}
		return array(
            $iSize,
            disk_free_space($this->path)
        );

    }	
}
