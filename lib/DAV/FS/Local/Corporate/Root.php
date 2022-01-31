<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Local\Corporate;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Root extends Directory 
{
	protected $storage = \Aurora\System\Enums\FileStorageType::Corporate;

	public function __construct() 
	{
		$path = \Aurora\System\Api::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT;
		
		if (!\file_exists($path))
		{
			\mkdir($path);
		}

		$path = $path . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_CORPORATE;

		if (!file_exists($path))
		{
			\mkdir($path);
		}

		$oTenant = $this->getTenant();
		if ($oTenant) 
		{
			$path = $path . '/' . $oTenant->Id;
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
		$mResult = [0, 0];
		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($this->UserPublicId);
		if ($oUser)
		{
			$aQuota = \Aurora\System\Api::GetModuleDecorator('Files')->GetQuota($oUser->Id, $this->getName());
			$mResult = [
				(int) $aQuota['Used'],
				(int) $aQuota['Limit']
			];
		}
		
		return $mResult;
	}
}
