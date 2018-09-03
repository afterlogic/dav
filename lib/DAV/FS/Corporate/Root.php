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

		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($this->UserPublicId);
		$aQuota = \Aurora\System\Api::GetModuleDecorator('Files')->GetQuota($oUser->EntityId, $this->getName());
		
		return array(
            $aQuota['Used'],
            $aQuota['Limit']
        );
		
	}
	
}
