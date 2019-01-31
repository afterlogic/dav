<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS;

class Plugin extends \Sabre\DAV\ServerPlugin {

    /**
     * @var int $iUserId
     */
    protected $iUserId = null;
	
	/**
	 * @var \CApiTenantsManager
	 */
	protected $oApiTenants = null;	
	
	/**
	 * @var \CApiMinManager
	 */
	protected $oApiMin = null;
	
	/**
	 * @var \CApiUsersManager
	 */
	protected $oApiUsers= null;	
	
	protected $sOldPath = null;
	protected $sOldID = null;

	protected $sNewPath = null;
	protected $sNewID = null;

	/*
     * Returns a plugin name.
     *
     * Using this name other plugins will be able to access other plugins
     * using DAV\Server::getPlugin
     *
     * @return string
     */
    function getPluginName() {

        return 'files';

    }	
	
	public function getMinMan()
	{
		if ($this->oApiMin == null) {
			
			$this->oApiMin = \Aurora\System\Api::Manager('min');
		}
		return $this->oApiMin;
	}
	
    public function getUser()
	{	
		if (!isset($this->iUserId)) {
			
			$this->iUserId = \Afterlogic\DAV\Server::getUser();
		}
		return $this->iUserId; 
	}
	
	/**
     * Initializes the plugin
     *
     * @param \Sabre\DAV\Server $server
     * @return void
     */
    public function initialize(\Sabre\DAV\Server $server) {

		$server->on('beforeMethod', [$this, 'beforeMethod']);   
		$server->on('beforeBind', [$this, 'beforeBind'], 30);
		$server->on('afterUnbind', [$this, 'afterUnbind'], 30);
        $server->on('propFind',         [$this, 'propFind']);
		
	}

    /**
     * Returns a list of supported features.
     *
     * This is used in the DAV: header in the OPTIONS and PROPFIND requests.
     *
     * @return array
     */
    public function getFeatures() {

        return ['files'];

	}
	
	public static function getPathByStorage($sUserPublicId, $sStorage)
	{
		$sPath = null;

		$oUser = \Aurora\System\Api::GetModuleDecorator('Core')->GetUserByPublicId($sUserPublicId);

		switch ($sStorage)
		{
			case 'personal':
				$sPath = self::getPersonalPath();
				if ($oUser) {
			
					$sPath = $sPath . '/' . $oUser->UUID;
					if (!\file_exists($sPath)) {
						
						\mkdir($sPath, 0777, true);
					}
				}
				break;
			case 'corporate':
				$sPath = self::getCorporatePath();
				if ($oUser) {
			
					$sPath = $sPath . '/' . $oUser->IdTenant;
					if (!\file_exists($sPath)) {
						
						\mkdir($sPath, 0777, true);
					}
				}
				break;
			case 'shared':
				$sPath = self::getSharedPath();
				break;
		}
		
		return $sPath;
	}
	
	public static function getPersonalPath() {
		
		return ltrim(
				\Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
				\Afterlogic\DAV\Constants::FILESTORAGE_PATH_PERSONAL, '/'
		);		
	}	
	
	public static function getCorporatePath() {
		
		return ltrim(
				\Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
				\Afterlogic\DAV\Constants::FILESTORAGE_PATH_CORPORATE, '/'
		);		
	}	

	public static function getSharedPath() {
		
		return ltrim(
				\Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT . 
				\Afterlogic\DAV\Constants::FILESTORAGE_PATH_SHARED, '/'
		);		
	}	

	public static function isFilestoragePrivate($path)
	{
		return (strpos($path, self::getPersonalPath()) !== false);
	}
	
	public static function isFilestorageCorporate($path)
	{
		return (strpos($path, self::getCorporatePath()) !== false);
	}
	
	public static function isFilestorageShared($path)
	{
		return (strpos($path, self::getSharedPath()) !== false);
	}

	public static function getTypeFromPath($path)
	{
		$sResult = \Aurora\System\Enums\FileStorageType::Personal;
		if (self::isFilestoragePrivate($path)) {
			
			$sResult = \Aurora\System\Enums\FileStorageType::Personal;
		}
		if (self::isFilestorageCorporate($path)) {
			
			$sResult = \Aurora\System\Enums\FileStorageType::Corporate;
		}
		if (self::isFilestorageShared($path)) {
			
			$sResult = \Aurora\System\Enums\FileStorageType::Shared;
		}
		return $sResult;
	}

	public static function getFilePathFromPath($path)
	{
		$sPath = '';
		if (self::isFilestoragePrivate($path)) {
			
			$sPath = self::getPersonalPath();
		}
		if (self::isFilestorageCorporate($path)) {
			
			$sPath = self::getCorporatePath();
		}
		if (self::isFilestorageShared($path)) {
			
			$sPath = self::getSharedPath();
		}
		
		return str_replace($sPath, '', $path);
	}

	function beforeMethod($methodName, $uri) {

	  if ($methodName === 'MOVE') {
		  
		  $GLOBALS['__FILESTORAGE_MOVE_ACTION__'] = true;
	  }
	  return true;

	}

	/**
     * @param string $path
     * @throws \Sabre\DAV\Exception\NotAuthenticated
     * @return bool
     */
    public function beforeBind($path)
    {
		if (self::isFilestoragePrivate($path) || 
				self::isFilestorageCorporate($path)) {
			
			$iUserId = $this->getUser();
			if ($iUserId) {
				
				$iType = self::getTypeFromPath($path);
				$sFilePath = self::getFilePathFromPath(dirname($path));
				$sFileName = basename($path);

				$this->sNewPath = $path;
				$this->sNewID = implode('|', array($iUserId, $iType, $sFilePath, $sFileName));
			}
		}
		return true;
	}
	
	/**
     * @param string $path
     * @throws \Sabre\DAV\Exception\NotAuthenticated
     * @return bool
     */
    public function afterUnbind($path)
    {
		if (self::isFilestoragePrivate($path) || self::isFilestorageCorporate($path)) {
			
			$iUserId = $this->getUser();

			if ($iUserId) {
				
 				$iType = self::getTypeFromPath($path);
				$sFilePath = self::getFilePathFromPath(dirname($path));
				$sFileName = basename($path);

				$oMin = $this->getMinMan();
				$this->sOldPath = $path;
				$this->sOldID = implode('|', array($iUserId, $iType, $sFilePath, $sFileName));
				$aData = $oMin->getMinByID($this->sOldID);
				
				if (isset($this->sNewPath)) {
					
//					$node = $this->server->tree->getNodeForPath($this->sNewPath);
//					\Aurora\System\Api::LogObject($node, \ELogLevel::Full, 'fs-');
				}
				
				if (isset($this->sNewID) && !empty($aData['__hash__'])) {
					
					$aNewData = explode('|', $this->sNewID);
					$aParams = array(
						'Type' => $aNewData[1],
						'Path' => $aNewData[2],
						'Name' => $aNewData[3],
						'Size' => $aData['Size']
					);
					$oMin->updateMinByID($this->sOldID, $aParams, $this->sNewID);
				} else {
					
					$oMin->deleteMinByID($this->sOldID);
				}
			}
		}
	    $GLOBALS['__FILESTORAGE_MOVE_ACTION__'] = false;
		return true;
	}

    /**
     * This method is called when properties are retrieved.
     *
     * Here we add all the default properties.
     *
     * @param \Sabre\DAV\PropFind $propFind
     * @param \Sabre\DAV\INode $node
     * @return void
     */
    function propFind(\Sabre\DAV\PropFind $propFind, \Sabre\DAV\INode $node) {

        $propFind->handle('{DAV:}displayname', function() use ($node) {

			if ($node instanceof \Afterlogic\DAV\FS\Directory || $node instanceof \Afterlogic\DAV\FS\File)
			{
				return $node->getDisplayName();
			}

        });
	}
	
}