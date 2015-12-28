<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS;

class RootPersonal extends Directory{
	
	private $rootPath = null;

	public function initPath() {
		
		$oAccount = $this->getAccount();
		if ($this->rootPath === null && $oAccount instanceof \CAccount) {
			
			$this->rootPath = $this->path . '/' . $oAccount->IncomingMailLogin;
			if (!file_exists($this->rootPath)) {
				
				mkdir($this->rootPath, 0777, true);
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
		$aResult = \api_Utils::GetDirectorySize($this->path);
		if ($aResult && $aResult['size']) {
			
			$iSize = (int) $aResult['size'];
		}
		return array(
            $iSize,
            0
        );

    }	
}
