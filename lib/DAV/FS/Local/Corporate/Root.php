<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Local\Corporate;

class Root extends Directory 
{
	public function __construct() 
	{
		$path = \Aurora\System\Api::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_CORPORATE;
		if (!file_exists($path))
		{
			\mkdir($path);
		}

		$oTenant = $this->getTenant();
		if ($oTenant) 
		{
			$path = $path . '/' . $oTenant->EntityId;
			if (!\file_exists($path)) 
			{
				\mkdir($path, 0777, true);
			}
		}
		parent::__construct($path);
	}
	
	public function getName() 
	{
        return \Aurora\System\Enums\FileStorageType::Corporate;
    }	

	public function setName($name) 
	{
		throw new \Sabre\DAV\Exception\Forbidden();
	}

	public function delete() 
	{
		throw new \Sabre\DAV\Exception\Forbidden();
	} 	
	
	public function getQuotaInfo() 
	{
		$mResult = [0, 0];
		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($this->UserPublicId);
		if ($oUser)
		{
			$aQuota = \Aurora\System\Api::GetModuleDecorator('Files')->GetQuota($oUser->EntityId, $this->getName());
			$mResult = [
				$aQuota['Used'],
				$aQuota['Limit']
			];
		}
		
		return $mResult;
	}
}
