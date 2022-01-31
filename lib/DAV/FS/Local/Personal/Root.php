<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Local\Personal;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Root extends Directory 
{
	protected $storage = \Aurora\System\Enums\FileStorageType::Personal;

	public function __construct($sUserPublicId = null) 
	{
		$path = \Aurora\System\Api::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT;
		
		if (!\file_exists($path))
		{
			\mkdir($path);
		}

		$path = $path . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_PERSONAL;

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
        return $this->storage;
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
			$aQuota = \Aurora\Modules\Files\Module::Decorator()->GetQuota($oUser->Id, $this->getName());
			return [
				(int) $aQuota['Used'],
				(int) $aQuota['Limit']
			];
		}
    }	
}
