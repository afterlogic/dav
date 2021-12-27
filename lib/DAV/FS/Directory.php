<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS;

use Afterlogic\DAV\Server;

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
	 * @var object $UserObject
	 */
	protected $UserObject = null;

	/**
	 * @var \Aurora\Modules\Core\Models\Tenant
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

	public function childExists($name)
	{
		$mResult = false;
		try
		{
			$mResult = !!$this->getChild($name);
		}
		catch (\Exception $oEx) {}

		return $mResult;
	}

	public function createDirectory($name)
	{
		if ($this->childExists($name)) throw new \Sabre\DAV\Exception\Conflict('Can\'t create a directory');

		parent::createDirectory($name);
    }

	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = [])
	{
		$result = false;
		if (!$this->childExists($name)) {
			$result = parent::createFile($name);
		}
		$oFile = $this->getChild($name);

		if ($oFile instanceof \Afterlogic\DAV\FS\File) {
			$oFile->patch($data, $rangeType, $offset);
		}

		$aProps = $oFile->getProperties(['Owner', 'ExtendedProps']);

		if (!isset($aProps['Owner'])) {
			$aProps['Owner'] = $this->getUser();
		}
		$aCurrentExtendedProps = [];
		if (isset($aProps['ExtendedProps'])) {
			$aCurrentExtendedProps = $aProps['ExtendedProps'];
		}
		foreach ($extendedProps as $sPropName => $propValue) {
			if ($propValue === null) {
				if (isset($aCurrentExtendedProps[$sPropName])) {
					unset($aCurrentExtendedProps[$sPropName]);
				}
			} else {
				$aCurrentExtendedProps[$sPropName] = $propValue;
			}
		}
		$aProps['ExtendedProps'] = $aCurrentExtendedProps;

		$oFile->updateProperties($aProps);

		if (!$this->updateQuota()) {
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

		foreach ($aChildren as $iKey => $oChild) {
			if ($oChild->getName() === '.sabredav' || ($oChild instanceof HistoryDirectory)) {
				unset($aChildren[$iKey]);
			}
		}

		return $aChildren;
    }

	public function delete()
	{
		$this->deleteResourceData();

        // Deleting all children
        foreach (parent::getChildren() as $child) $child->delete();

        // Removing the directory itself
        rmdir($this->path);

        $this->deleteShares();

		$this->updateQuota();

		return true;
	}

	public function Search($pattern, $path = null)
	{
		$aResult = [];

		$path = ($path === null) ? $this->getPath() : $path;
		$aItems = \Aurora\System\Utils::SearchFiles($path, $pattern);
		if ($aItems)
		{
			foreach ($aItems as $sItem)
			{
				$sItemName = \basename($sItem);
				$ext = strtolower(substr($sItemName, -5));
				if ($sItemName !== '.sabredav' && !(is_dir($sItem) && $ext === '.hist'))
				{
					$aResult[] = is_dir($sItem) ? new self($this->getStorage(), $sItem) : new File($this->getStorage(), $sItem);
				}
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
