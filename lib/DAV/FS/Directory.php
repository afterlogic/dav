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
	 * @var int $iUserId
	 */
	protected $iUserId = null;	
	
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
		if ($this->iUserId === null) {
			
			$this->iUserId = \Afterlogic\DAV\Server::getInstance()->getUser();
		}
		return $this->iUserId;
	}
	
	public function setUser($iUserId)
	{
		$this->iUserId = $iUserId;
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
	
	public function initPath() {
		
    }

	public function getPath() {

        return $this->path;

    }

    public function createDirectory($name) {

		$this->initPath();
		
        if ($name=='.' || $name=='..') {
			
			throw new \Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');
		}
        $newPath = $this->path . '/' . $name;
		
		if (!is_dir($newPath)) {
			
			if (!@mkdir($newPath, 0777, true))
			{
				throw new \Sabre\DAV\Exception('Can\'t create a directory');
			}
		}
    }

	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = []) {

		$this->initPath();
		
		$bFileExists = $this->childExists($name);
		if (!($bFileExists))
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
		
		if (!isset($aProps['Owner'])) {
			
			$aProps['Owner'] = $this->getUser();
		}

		$aProps['ExtendedProps'] = $extendedProps;

		$oFile->updateProperties($aProps);

		if (!$this->updateQuota()) {
			
			$oFile->delete();
			throw new \Sabre\DAV\Exception\InsufficientStorage();
		}
    }

    public function getChild($name) {

		$this->initPath();
		
        $path = $this->path . '/' . trim($name, '/');

        if (!file_exists($path)) {
			
			throw new \Sabre\DAV\Exception\NotFound(
					'File with name ' . $path . ' could not be located'
			);
		}

		return is_dir($path) ? new Directory($path) : new File($path);
    }	
	
	public function getChildren() {

		$this->initPath();
		
		$nodes = array();
		
		if(!file_exists($this->path)) {
			
			mkdir($this->path);
		}
		
        foreach(scandir($this->path) as $node) {
			
			if($node!='.' && $node!='..' && $node!== '.sabredav' && 
					$node!== API_HELPDESK_PUBLIC_NAME)  {
				
				$nodes[] = $this->getChild($node);
			}
		}
        return $nodes;

    }
	
    public function childExists($name) {

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
		if ($aItems) {
			
			foreach ($aItems as $sItem) {
				
				$aResult[] = is_dir($sItem) ? new Directory($sItem) : new File($sItem);
			}
		}
		
		return $aResult;
	}
	
	public function getRootPath($sType = \EFileStorageTypeStr::Personal)
	{
		$sRootPath = '';
		$iUserId = $this->getUser();
		
		if ($sType === \EFileStorageTypeStr::Corporate) {

			$sRootPath = \Aurora\System\Api::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
				\Afterlogic\DAV\Constants::FILESTORAGE_PATH_CORPORATE . '/' . 0;
		} else if ($sType === \EFileStorageTypeStr::Shared) {

			$sRootPath = \Aurora\System\Api::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
					\Afterlogic\DAV\Constants::FILESTORAGE_PATH_SHARED . '/' . $iUserId;
		} else {

			$sRootPath = \Aurora\System\Api::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
					\Afterlogic\DAV\Constants::FILESTORAGE_PATH_PERSONAL . '/' . $iUserId;
		}
		
		return $sRootPath;
	}
	
	public function getFullQuotaInfo()
	{
		$iFreeSize = 0;

		$sRootPath = $this->getRootPath(\EFileStorageTypeStr::Personal);
		$aSize = \Aurora\System\Utils::GetDirectorySize($sRootPath);
		$iUsageSize = (int) $aSize['size'];

		$sRootPath = $this->getRootPath(\EFileStorageTypeStr::Corporate);
		$aSize = \Aurora\System\Utils::GetDirectorySize($sRootPath);
		$iUsageSize += (int) $aSize['size'];

		$iUserId = $this->getUser();
		if ($iUserId) {
			
			$oTenant = $this->getTenant();
			if ($oTenant) {
				
				$iFreeSize = ($oTenant->FilesUsageDynamicQuotaInMB * 1024 * 1024) - $iUsageSize;
			}
		}
		
		return array($iUsageSize, $iFreeSize);
	}
	
	public function updateQuota()
	{
		if (isset($GLOBALS['__FILESTORAGE_MOVE_ACTION__']) && 
				$GLOBALS['__FILESTORAGE_MOVE_ACTION__']) {
			
			return true;
		}
		
		$iSizeUsage = 0;
		$aQuota = $this->getFullQuotaInfo();
		if (isset($aQuota[0])) {
			
			$iSizeUsage = $aQuota[0];
		}
		$oTenant = $this->getTenant();
		if (!isset($oTenant)) {
			
			return true;
		} else {
			
			$oTenantsMan = $this->getTenantsMan();
			if ($oTenantsMan) {
				
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
    public function updateProperties($properties) {

        $resourceData = $this->getResourceData();

        foreach($properties as $propertyName=>$propertyValue) {

            // If it was null, we need to delete the property
            if (is_null($propertyValue)) {
                if (isset($resourceData['properties'][$propertyName])) {
                    unset($resourceData['properties'][$propertyName]);
                }
            } else {
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
    function getProperties($properties) {

        $resourceData = $this->getResourceData();

        // if the array was empty, we need to return everything
        if (!$properties) return $resourceData['properties'];

        $props = array();
        foreach($properties as $property) {
            if (isset($resourceData['properties'][$property])) $props[$property] = $resourceData['properties'][$property];
        }

        return $props;

    }

    /**
     * Returns the path to the resource file
     *
     * @return string
     */
    protected function getResourceInfoPath() {

        list($parentDir) = \Sabre\Uri\split($this->path);
        return $parentDir . '/.sabredav';

    }

    /**
     * Returns all the stored resource information
     *
     * @return array
     */
    protected function getResourceData() {

        $path = $this->getResourceInfoPath();
        if (!file_exists($path)) return array('properties' => array());

        // opening up the file, and creating a shared lock
        $handle = fopen($path,'r');
        flock($handle,LOCK_SH);
        $data = '';

        // Reading data until the eof
        while(!feof($handle)) {
            $data.=fread($handle,8192);
        }

        // We're all good
        fclose($handle);

        // Unserializing and checking if the resource file contains data for this file
        $data = unserialize($data);
        if (!isset($data[$this->getName()])) {
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
    protected function putResourceData(array $newData) {

        $path = $this->getResourceInfoPath();

        // opening up the file, and creating a shared lock
        $handle = fopen($path,'a+');
        flock($handle,LOCK_EX);
        $data = '';

        rewind($handle);

        // Reading data until the eof
        while(!feof($handle)) {
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
	
}