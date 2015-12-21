<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS;

class RootPublic extends Directory {
	
	private $rootPath = null;

    public function initPath() {
		
		$sUserName = \Afterlogic\DAV\Auth\Backend::getInstance()->getCurrentUser();
		if ($this->rootPath === null)
		{
			if (isset($sUserName))
			{
				$oAccount = \Afterlogic\DAV\Utils::GetAccountByLogin($sUserName);
				if ($oAccount)
				{
					$this->rootPath = $this->path . '/' . $oAccount->IdTenant;
					if (!file_exists($this->rootPath))
					{
						mkdir($this->rootPath, 0777, true);
					}
				}
			}
			
		}
		if ($this->rootPath !== null)
		{
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
		$aResult = \api_Utils::GetDirectorySize($this->path);
		if ($aResult && $aResult['size'])
		{
			$Size = (int) $aResult['size'];
		}
		return array(
            $Size,
            0
        );	
	}
	
}
