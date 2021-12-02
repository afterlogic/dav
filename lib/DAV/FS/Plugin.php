<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS;

use function GuzzleHttp\json_encode;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Plugin extends \Sabre\DAV\ServerPlugin {

	/**
	 *
	 * @var \Sabre\DAV\Server $server
	 */
	protected $server = null;

    /**
     * @var string $sUserPublicId
     */
    protected $sUserPublicId = null;

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

	/**
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

	/***
	 *
	 */
	public function getMinModule()
	{
		if ($this->oMinModule == null)
		{
			$this->oMinModule = \Aurora\Modules\Min\Module::getInstance();
		}
		return $this->oMinModule;
	}

	/**
	 *
	 */
	public function getUser()
	{
		if (!isset($this->sUserPublicId))
		{
			$this->sUserPublicId = \Afterlogic\DAV\Server::getUser();
		}
		return $this->sUserPublicId;
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
        $server->on('propFind', [$this, 'propFind'], 250);
		$server->on('method:MOVE', [$this, 'move'], 30);
		$server->on('method:GET', [$this, 'methodGet'], 10);
		$server->on('afterMethod:PUT', [$this, 'afterMethodPut'], 10);
		$this->server = $server;
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

	/**
	 *
	 */
	public static function getStoragePath($sUserPublicId, $sStorage)
	{
		$sPath = null;

		$oServer = \Afterlogic\DAV\Server::getInstance();
		$sUser = $oServer->getUser();
		$oServer->setUser($sUserPublicId);
		$oNode = $oServer->tree->getNodeForPath('files/'. $sStorage);
		if ($oNode)
		{
			$sPath = $oNode->getPath();
		}
		$oServer->setUser($sUser);

		return $sPath;
	}

	/**
	 *
	 */
	public function getNodeFromPath($path)
	{
		$oServer = \Afterlogic\DAV\Server::getInstance();
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
		list($sFilePath, $sFileName) = \Sabre\Uri\split($path);

		$oNode = $this->getNodeFromPath($sFilePath);
		if (isset($oNode) && $oNode instanceof \Sabre\DAV\FS\Node)
		{
			$sUserPublicId = $this->getUser();
			if ($sUserPublicId)
			{
				$sType = $oNode->getStorage();

				$this->sNewPath = $path;
				$this->sNewID = implode('|', [$sUserPublicId, $sType, $sFilePath, $sFileName]);
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
		list($sFilePath, $sFileName) = \Sabre\Uri\split($path);

		$oNode = $this->getNodeFromPath($sFilePath);
		if (isset($oNode) && $oNode instanceof \Sabre\DAV\FS\Node)
		{
			$sUserPublicId = $this->getUser();

			if ($sUserPublicId)
			{
 				$sType = $oNode->getStorage();

				$oMin = $this->getMinModule();
				$this->sOldPath = $path;
				$this->sOldID = implode('|', [$sUserPublicId, $sType, $sFilePath, $sFileName]);
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
		if ($node instanceof \Afterlogic\DAV\FS\Directory || $node instanceof \Afterlogic\DAV\FS\File)
		{
			$propFind->handle('{DAV:}displayname', $node->getDisplayName());
			if ($node instanceof \Afterlogic\DAV\FS\Shared\Directory || $node instanceof \Afterlogic\DAV\FS\Shared\File)
			{
				$propFind->handle('{DAV:}share-path', $node->getSharePath());
			}
		}

		if ($node instanceof \Afterlogic\DAV\FS\File)
		{
			$mExtendedProps = $node->getProperty('ExtendedProps');
			$aExtendedProps = is_array($mExtendedProps) ? $mExtendedProps : [];
			$propFind->handle('{DAV:}extended-props-as-json', \json_encode($aExtendedProps));
			$propFind->handle('{DAV:}extended-props', $aExtendedProps);
		}
	}

	/**
	 *
	 */
	function move($request, $response)
	{
		$GLOBALS['__FILESTORAGE_MOVE_ACTION__'] = true;
		return true;
	}

	function methodGet($request, $response)
	{
		$node = $this->server->tree->getNodeForPath($request->getPath());
		if ($node instanceof \Afterlogic\DAV\FS\File)
		{
			$mExtendedProps = $node->getProperty('ExtendedProps');
			$aExtendedProps = is_array($mExtendedProps) ? $mExtendedProps : [];
			$aHeaderValues = [];
			foreach ($aExtendedProps as $key => $value)
			{
				if ($key === 'ParanoidKey')
				{
					$value = \str_replace("\r\n", '\r\n', \addslashes(\trim($value, '"')));
				}
				$aHeaderValues[] = $key . "=" . '"' . $value . '"';
			}
			$response->setHeader('Extended-Props', \implode("; ", $aHeaderValues));
		}
	}

	function afterMethodPut($request, $response)
	{
		$node = $this->server->tree->getNodeForPath($request->getPath());
		if ($node instanceof \Afterlogic\DAV\FS\File)
		{
			$mExtendedProps = $node->getProperty('ExtendedProps');
			$aExtendedProps = is_array($mExtendedProps) ? $mExtendedProps : [];

			foreach ($request->getHeaders() as $sKey => $aHeader)
			{
				if (\strtolower($sKey) === 'extended-props')
				{
					$aValues = \explode(";", $aHeader[0]);
					foreach ($aValues as $sValue)
					{
						if (!empty($sValue))
						{
							list($sKeyValue, $sValue) = \explode("=", \trim($sValue), 2);
							$sValue = \trim($sValue, '"');
							if (isset($aExtendedProps[$sKeyValue]) && empty($sValue))
							{
								unset($aExtendedProps[$sKeyValue]);
							}
							else
							{
								if ($sKeyValue === 'ParanoidKey')
								{
									$aExtendedProps[$sKeyValue] = \stripslashes(\str_replace('\r\n', "\r\n", \trim($sValue, '"')));
								}
								else
								{
									$aExtendedProps[$sKeyValue] = \trim($sValue, '"');
								}
							}
						}
					}
				}
			}
			$node->setProperty('ExtendedProps', $aExtendedProps);
		}
	}
}
