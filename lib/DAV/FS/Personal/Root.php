<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Personal;

class Root extends Directory {
	
	public function __construct($path, $sUserPublicId = null) {
		
		if (empty($sUserPublicId))
		{
			$sUserPublicId = $this->getUser();
		}
		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);
		
		if ($oUser) {
			
			$path = $path . '/' . $oUser->UUID;
			if (!\file_exists($path)) {
				
				\mkdir($path, 0777, true);
			}
		}
		parent::__construct($path);
	}

    public function getName() {

        return \Aurora\System\Enums\FileStorageType::Personal;

    }	
	
	public function setName($name) {

        throw new \Sabre\DAV\Exception\Forbidden();

    }

    public function delete() {

        throw new \Sabre\DAV\Exception\Forbidden();

    }
	
    public function getQuotaInfo() {

		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($this->UserPublicId);
		$aQuota = \Aurora\System\Api::GetModuleDecorator('Files')->GetQuota($oUser->EntityId, $this->getName());
		
		return array(
            $aQuota['Used'],
            $aQuota['Limit']
        );

    }	
}
