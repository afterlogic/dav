<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Local\Personal;

class Root extends Directory 
{
	public function __construct($sUserPublicId = null) 
	{
		$path = \Aurora\System\Api::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_PERSONAL;
		if (!\file_exists($path))
		{
			\mkdir($path);
		}

		if (empty($sUserPublicId))
		{
			$sUserPublicId = $this->getUser();
		}
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($sUserPublicId);
		
		if ($oUser) 
		{
			$path = $path . '/' . $oUser->UUID;
			if (!\file_exists($path)) 
			{
				\mkdir($path, 0777, true);
			}
		}
		parent::__construct($path);
	}

	public function getName() 
	{
        return \Aurora\System\Enums\FileStorageType::Personal;
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
		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($this->UserPublicId);
		if ($oUser)
		{
			$aQuota = \Aurora\Modules\Files\Module::Decorator()->GetQuota($oUser->EntityId, $this->getName());
			return [
				$aQuota['Used'],
				$aQuota['Limit']
			];
		}
    }	
}
