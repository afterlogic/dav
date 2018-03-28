<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS;

class Directory extends \Sabre\DAV\FSExt\Directory {
    
	/**
	 * @var \CApiTenantsManager
	 */
	protected $oApiTenants = null;	
	
	/**
	 * @var \CApiUsersManager
	 */
	protected $oApiUsers = null;	
	
	/**
	 * @var string $UserPublicId
	 */
	protected $UserPublicId = null;	
	
	/**
	 * @var \CTenant
	 */
	protected $oTenant = null;
	
	public function getTenantsMan()
	{
		if ($this->oApiTenants === null) {
			
			$this->oApiTenants = \Aurora\System\Api::GetSystemManager('tenants');
		}
		
		return $this->oApiTenants;
	}

	public function getUser()
	{
		if ($this->UserPublicId === null) {
			
			$this->UserPublicId = \Afterlogic\DAV\Server::getUser();
		}
		return $this->UserPublicId;
	}
	
	public function getTenant()
	{
		if ($this->oTenant == null) {
			// TODO: 
/*			$oAccount = $this->getAccount();
			if ($oAccount !== null) {
				
				$oApiTenants = $this->getTenantsMan();
				if ($oApiTenants) {
					
					$this->oTenant = $oApiTenants->getTenantById($oAccount->IdTenant);
				}
			}
 * 
 */
		}
		
		return $this->oTenant;
	}
	
	public function initPath() {}

	public function getPath() 
	{
        return $this->path;
    }
    
	public function createDirectory($name) 
	{
		$this->initPath();
		
		if ($this->childExists($name)) throw new \Sabre\DAV\Exception\Conflict('Can\'t create a directory');

		parent::createDirectory($name);
    }

	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = []) 
	{
		$this->initPath();
		
		if (!$this->childExists($name))
		{
			if ($rangeType === 0)
			{
				parent::createFile($name, $data);
			}
			else
			{
				parent::createFile($name);
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
		
		$aProps = $oFile->getProperties(array('Owner'));
		
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
    }

    public function getChild($name) {

		$this->initPath();
		
        $path = $this->path . '/' . trim($name, '/');

        if (!file_exists($path)) throw new \Sabre\DAV\Exception\NotFound('File could not be located');
        if ($name == '.' || $name == '..') throw new \Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');

		return is_dir($path) ? new self($path) : new File($path);
    }	
	
	public function getChildren() 
	{
		$this->initPath();
		
		return parent::getChildren();
    }
	
    public function childExists($name) 
	{
		$this->initPath();
		
		return parent::childExists($name);
    }

    public function delete() {

		$this->initPath();
		
		parent::delete();
		
		$this->updateQuota();
    }	
	
	public function Search($pattern, $path = null) 
	{
		$aResult = array();
		
		$this->initPath();
		
		$path = ($path === null) ? $this->path : $path;
		$aItems = \Aurora\System\Utils::SearchFiles($path, $pattern);
		if ($aItems) 
		{
			foreach ($aItems as $sItem) 
			{
				$aResult[] = is_dir($sItem) ? new self($sItem) : new File($sItem);
			}
		}
		
		return $aResult;
	}
	
	public function getRootPath($sType = \Aurora\System\Enums\FileStorageType::Personal)
	{
		$sRootPath = '';
		$UserPublicId = $this->getUser();
		
		if ($sType === \Aurora\System\Enums\FileStorageType::Corporate) 
		{
			$sRootPath = \Aurora\System\Api::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
				\Afterlogic\DAV\Constants::FILESTORAGE_PATH_CORPORATE . '/' . 0;
		} 
		else if ($sType === \Aurora\System\Enums\FileStorageType::Shared) 
		{
			$sRootPath = \Aurora\System\Api::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
					\Afterlogic\DAV\Constants::FILESTORAGE_PATH_SHARED . '/' . $UserPublicId;
		} 
		else 
		{
			$sRootPath = \Aurora\System\Api::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
					\Afterlogic\DAV\Constants::FILESTORAGE_PATH_PERSONAL . '/' . $UserPublicId;
		}
		
		return $sRootPath;
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
		
		return array($iUsageSize, $iFreeSize);
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
			$oTenantsMan = $this->getTenantsMan();
			if ($oTenantsMan) 
			{
				return $oTenantsMan->allocateFileUsage($oTenant, $iSizeUsage);
			}
		}
	}
	
	public function getProperty($sName)
	{
		$aData = $this->getResourceData();
		return isset($aData[$sName]) ? $aData[$sName] : null;
	}
	
	public function setProperty($sName, $mValue)
	{
		$aData = $this->getResourceData();
		$aData[$sName] = $mValue;
		$this->putResourceData($aData);
	}	
	
    /**
     * Updates properties on this node,
     *
     * @param array $properties
     * @see Sabre\DAV\IProperties::updateProperties
     * @return bool|array
     */
    public function updateProperties($properties) 
	{
        $resourceData = $this->getResourceData();

        foreach($properties as $propertyName=>$propertyValue) 
		{
            // If it was null, we need to delete the property
            if (is_null($propertyValue)) 
			{
                if (isset($resourceData['properties'][$propertyName])) 
				{
                    unset($resourceData['properties'][$propertyName]);
                }
            } 
			else 
			{
                $resourceData['properties'][$propertyName] = $propertyValue;
            }

        }

        $this->putResourceData($resourceData);
        return true;
    }

    /**
     * Returns a list of properties for this nodes.;
     *
     * The properties list is a list of propertynames the client requested, encoded as xmlnamespace#tagName, for example: http://www.example.org/namespace#author
     * If the array is empty, all properties should be returned
     *
     * @param array $properties
     * @return array
     */
    function getProperties($properties) 
	{
        $resourceData = $this->getResourceData();

        // if the array was empty, we need to return everything
        if (!$properties) return $resourceData['properties'];

        $props = array();
        foreach($properties as $property) 
		{
            if (isset($resourceData['properties'][$property])) $props[$property] = $resourceData['properties'][$property];
        }

        return $props;

    }

    /**
     * Returns the path to the resource file
     *
     * @return string
     */
    protected function getResourceInfoPath() 
	{
        list($parentDir) = \Sabre\Uri\split($this->path);
        return $parentDir . '/.sabredav';
    }

    /**
     * Returns all the stored resource information
     *
     * @return array
     */
    protected function getResourceData() 
	{
        $path = $this->getResourceInfoPath();
        if (!file_exists($path)) return array('properties' => array());

        // opening up the file, and creating a shared lock
        $handle = fopen($path,'r');
        flock($handle,LOCK_SH);
        $data = '';

        // Reading data until the eof
        while(!feof($handle)) 
		{
            $data.=fread($handle,8192);
        }

        // We're all good
        fclose($handle);

        // Unserializing and checking if the resource file contains data for this file
        $data = unserialize($data);
        if (!isset($data[$this->getName()])) 
		{
            return array('properties' => array());
        }

        $data = $data[$this->getName()];
        if (!isset($data['properties'])) $data['properties'] = array();
        return $data;
    }

    /**
     * Updates the resource information
     *
     * @param array $newData
     * @return void
     */
    protected function putResourceData(array $newData) 
	{
        $path = $this->getResourceInfoPath();

        // opening up the file, and creating a shared lock
        $handle = fopen($path,'a+');
        flock($handle,LOCK_EX);
        $data = '';

        rewind($handle);

        // Reading data until the eof
        while(!feof($handle)) 
		{
            $data.=fread($handle,8192);
        }

        // Unserializing and checking if the resource file contains data for this file
        $data = unserialize($data);
        $data[$this->getName()] = $newData;
        ftruncate($handle,0);
        rewind($handle);

        fwrite($handle,serialize($data));
        fclose($handle);

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
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->path), \RecursiveIteratorIterator::SELF_FIRST);
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