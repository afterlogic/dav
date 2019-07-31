<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Directory extends \Sabre\DAV\FSExt\Directory implements \Sabre\DAVACL\IACL
{
	use NodeTrait;
    use PropertyStorageTrait;
	
	/**
	 * @var string $UserPublicId
	 */
	protected $UserPublicId = null;	
	
	/**
	 * @var object $UserObject
	 */
	protected $UserObject = null;

	/**
	 * @var \Aurora\Modules\Core\Classes\Tenant
	 */
	protected $oTenant = null;
	
	public function __construct($storage, $path)
	{
		$this->storage = $storage;
		parent::__construct($path);
	}

	public function getUserObject()
	{
		if ($this->UserObject === null) 
		{
			$sUserPublicId = $this->getUser();
			if ($sUserPublicId !== null) 
			{
				$this->UserObject = \Aurora\Modules\Core\Module::getInstance()->GetUserByPublicId($sUserPublicId);
			}
		}
		return $this->UserObject;
	}

	public function getTenant()
	{
		if ($this->oTenant === null) 
		{
			$oUser = $this->getUserObject();
			if ($oUser)
			{
				$this->oTenant = \Aurora\Modules\Core\Module::getInstance()->GetTenantUnchecked($oUser->IdTenant);
			}
		}
		
		return $this->oTenant;
	}
	
	public function createDirectory($name) 
	{
		if ($this->childExists($name)) throw new \Sabre\DAV\Exception\Conflict('Can\'t create a directory');

		parent::createDirectory($name);
    }

	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = []) 
	{
		$result = false;
		if (!$this->childExists($name))
		{
			if ($rangeType === 0)
			{
				$result = parent::createFile($name, $data);
			}
			else
			{
				$result = parent::createFile($name);
			}
		}
		$oFile = $this->getChild($name);
		if ($oFile instanceof \Afterlogic\DAV\FS\File)
		{
			if ($rangeType !== 0)
			{
				$oFile->patch($data, $rangeType, $offset);
			}
		}
		
		$aProps = $oFile->getProperties(['Owner']);
		
		if (!isset($aProps['Owner'])) 
		{
			$aProps['Owner'] = $this->getUser();
		}

		$aProps['ExtendedProps'] = $extendedProps;

		$oFile->updateProperties($aProps);

		if (!$this->updateQuota()) 
		{
			$oFile->delete();
			throw new \Sabre\DAV\Exception\InsufficientStorage();
		}
		
		return $result;
    }

	public function getChild($name) 
	{
		if (strlen(trim($name)) === 0) throw new \Sabre\DAV\Exception\Forbidden('Permission denied to emty item');

        $path = $this->path . '/' . trim($name, '/');

        if (!file_exists($path)) throw new \Sabre\DAV\Exception\NotFound('File could not be located');
        if ($name == '.' || $name == '..') throw new \Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');

		return is_dir($path) ? new self($this->getStorage(), $path) : new File($this->getStorage(), $path);
    }	
	
	public function getChildren() 
	{
		$aChildren = parent::getChildren();
		
		foreach ($aChildren as $iKey => $oChild)
		{
			if ($oChild->getName() === '.sabredav')
			{
				unset($aChildren[$iKey]);
			}
		}
		
		return $aChildren;
    }
	
	public function delete() 
	{
		$this->deleteResourceData();
		
		if (\file_exists($this->path . '/.sabredav'))
		{
			\unlink($this->path . '/.sabredav');
		}
		
		$result = parent::delete();

        $this->deleteShares();
		
		$this->updateQuota();
		
		return $result;
	}	
	
    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name) 
    {
        list($parentPath, $oldName) = \Sabre\Uri\split($this->path);
        list(, $newName) = \Sabre\Uri\split($name);
        $newPath = $parentPath . '/' . $newName;
		
		$sRelativePath = $this->getRelativePath();

		$oldPathForShare = $sRelativePath . '/' .$oldName;
		$newPathForShare = $sRelativePath . '/' .$newName;

		$oSharedFiles = \Aurora\System\Api::GetModule('SharedFiles');
		if ($oSharedFiles)
		{
			$pdo = new Backend\PDO();
			$pdo->updateShare($this->getOwner(), $this->getStorage(), $oldPathForShare, $newPathForShare);
		}
	
        // We're deleting the existing resourcedata, and recreating it
        // for the new path.
        $resourceData = $this->getResourceData();
        $this->deleteResourceData();

        rename($this->path, $newPath);
        $this->path = $newPath;
        $this->putResourceData($resourceData);
    }	
	
	public function Search($pattern, $path = null) 
	{
		$aResult = [];
		
		$path = ($path === null) ? $this->path : $path;
		$aItems = \Aurora\System\Utils::SearchFiles($path, $pattern);
		if ($aItems) 
		{
			foreach ($aItems as $sItem) 
			{
				$aResult[] = is_dir($sItem) ? new self($this->getStorage(), $sItem) : new File($this->getStorage(), $sItem);
			}
		}
		
		return $aResult;
	}

	public function getFullQuotaInfo()
	{
		$iFreeSize = 0;

		$sRootPath = $this->getRootPath(\Aurora\System\Enums\FileStorageType::Personal);
		$aSize = \Aurora\System\Utils::GetDirectorySize($sRootPath);
		$iUsageSize = (int) $aSize['size'];

		$sRootPath = $this->getRootPath(\Aurora\System\Enums\FileStorageType::Corporate);
		$aSize = \Aurora\System\Utils::GetDirectorySize($sRootPath);
		$iUsageSize += (int) $aSize['size'];

		$UserPublicId = $this->getUser();
		if ($UserPublicId) 
		{
			$oTenant = $this->getTenant();
			if ($oTenant) 
			{
				$iFreeSize = ($oTenant->FilesUsageDynamicQuotaInMB * 1024 * 1024) - $iUsageSize;
			}
		}
		
		return [$iUsageSize, $iFreeSize];
	}

	public function updateQuota()
	{
		if (isset($GLOBALS['__FILESTORAGE_MOVE_ACTION__']) && $GLOBALS['__FILESTORAGE_MOVE_ACTION__']) 
		{
			return true;
		}
		
		$iSizeUsage = 0;
		$aQuota = $this->getFullQuotaInfo();
		if (isset($aQuota[0])) 
		{
			$iSizeUsage = $aQuota[0];
		}
		$oTenant = $this->getTenant();
		if (!isset($oTenant)) 
		{
			return true;
		} 
		else 
		{
			return true;
			// TODO:
/*
			$oTenantsMan = $this->getTenantsMan();
			if ($oTenantsMan) 
			{
				return $oTenantsMan->allocateFileUsage($oTenant, $iSizeUsage);
			}
 * 
 */
		}
	}
	
	public function getChildrenProperties()
	{
		return $this->getResourceData();
	}

    /**
     * Returns array of SplFileInfo
     *
     * @return array
     */
    public function getFileListRecursive()
    {
        $files = [];
        $items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->path), 
			\RecursiveIteratorIterator::SELF_FIRST
		);
        $excludedFiles = ['.sabredav'];

        foreach($items as $item) 
		{
            /* @var $item \SplFileInfo */
            if ($item->isFile() && $item->isReadable() && !in_array($item->getFilename(), $excludedFiles)) 
			{
                $files[] = $item;
            }
        }

        return $files;
    }
}
