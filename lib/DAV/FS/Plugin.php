<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS;

class Plugin extends \Sabre\DAV\ServerPlugin {

    /**
     * @var int $iUserId
     */
    protected $iUserId = null;
	
	/**
	 * @var \Aurora\Modules\Min\Module
	 */
	protected $oMinModule = null;
	
	/**
	 * @var string
	 */	
	protected $sOldPath = null;

	/**
	 * @var string
	 */	
	protected $sOldID = null;

	/**
	 * @var string
	 */	
	protected $sNewPath = null;

	/**
	 * @var string
	 */	
	protected $sNewID = null;

	/*
     * Returns a plugin name.
     *
     * Using this name other plugins will be able to access other plugins
     * using DAV\Server::getPlugin
     *
     * @return string
     */
	function getPluginName()
	{
        return 'files';
    }	
	
	public function getMinModule()
	{
		if ($this->oMinModule == null) 
		{
			$this->oMinModule = \Aurora\System\Api::GetModule('Min');
		}
		return $this->oMinModule;
	}
	
    public function getUser()
	{	
		if (!isset($this->iUserId)) 
		{
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
	public function initialize(\Sabre\DAV\Server $server) 
	{
		$server->on('beforeBind', [$this, 'beforeBind'], 30);
		$server->on('afterUnbind', [$this, 'afterUnbind'], 30);
        $server->on('propFind', [$this, 'propFind'], 30);
		$server->on('method:MOVE', [$this, 'move'], 30);   
	}

    /**
     * Returns a list of supported features.
     *
     * This is used in the DAV: header in the OPTIONS and PROPFIND requests.
     *
     * @return array
     */
	public function getFeatures() 
	{
        return ['files'];
	}
	
	public static function getStoragePath($sUserPublicId, $sStorage)
	{
		$sPath = null;

		$oServer = \Afterlogic\DAV\Server::getInstance();
		$oServer->setUser($sUserPublicId);
		$oNode = $oServer->tree->getNodeForPath('files/'. $sStorage);
		if ($oNode)
		{
			$sPath = $oNode->getPath();
		}

		return $sPath;
	}

	public function getNodeFromPath($path)
	{
		$oServer = \Afterlogic\DAV\Server::getInstance();
//		var_dump($this->getUser());
		$oServer->setUser($this->getUser());
		return $oServer->tree->getNodeForPath($path);
	}

	/**
     * @param string $path
     * @throws \Sabre\DAV\Exception\NotAuthenticated
     * @return bool
     */
    public function beforeBind($path)
    {
		$sFilePath = \dirname($path);
		$sFileName = \basename($path);
		
		$oNode = $this->getNodeFromPath($sFilePath);
		if (isset($oNode) && $oNode instanceof \Sabre\DAV\FS\Node)
		{
			$iUserId = $this->getUser();
			if ($iUserId) 
			{
				$sType = $oNode->getStorage();

				$this->sNewPath = $path;
				$this->sNewID = implode('|', [$iUserId, $sType, $sFilePath, $sFileName]);
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
		$sFilePath = \dirname($path);
		$sFileName = \basename($path);
		
		$oNode = $this->getNodeFromPath($sFilePath);
		if (isset($oNode) && $oNode instanceof \Sabre\DAV\FS\Node)
		{
			$iUserId = $this->getUser();

			if ($iUserId) 
			{
 				$sType = $oNode->getStorage();

				$oMin = $this->getMinModule();
				$this->sOldPath = $path;
				$this->sOldID = implode('|', [$iUserId, $sType, $sFilePath, $sFileName]);
				$aData = $oMin->getMinByID($this->sOldID);
				
				if (isset($this->sNewID) && !empty($aData['__hash__'])) 
				{
					$aNewData = explode('|', $this->sNewID);
					$aParams = [
						'Type' => $aNewData[1],
						'Path' => $aNewData[2],
						'Name' => $aNewData[3],
						'Size' => $aData['Size']
					];
					$oMin->updateMinByID($this->sOldID, $aParams, $this->sNewID);
				} 
				else 
				{
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
	function propFind(\Sabre\DAV\PropFind $propFind, \Sabre\DAV\INode $node) 
	{
		$propFind->handle('{DAV:}displayname', function() use ($node) {
			if ($node instanceof \Afterlogic\DAV\FS\Directory || $node instanceof \Afterlogic\DAV\FS\File)
			{
				return $node->getDisplayName();
			}

        });
	}

	function move($request, $response) 
	{
	  if ($request->getMethod() === 'MOVE') 
	  {
		  $GLOBALS['__FILESTORAGE_MOVE_ACTION__'] = true;
	  }

	  return true;
	}

}