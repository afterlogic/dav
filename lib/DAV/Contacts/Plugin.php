<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Contacts;

class Plugin extends \Sabre\DAV\ServerPlugin
{

    /**
     * Reference to main server object
     *
     * @var \Sabre\DAV\Server
     */
    private $oServer;
	
	private $oContactsDecorator;
	private $oDavContactsDecorator;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
		$this->oContactsDecorator = \Aurora\Modules\Contacts\Module::Decorator();
		$this->oDavContactsDecorator = \Aurora\Modules\DavContacts\Module::Decorator();
	}

    public function initialize(\Sabre\DAV\Server $oServer)
    {
        $this->oServer = $oServer;
		$this->oServer->on('beforeUnbind', array($this, 'beforeUnbind'),30);
        $this->oServer->on('afterUnbind', array($this, 'afterUnbind'),30);
		$this->oServer->on('afterWriteContent', array($this, 'afterWriteContent'), 30);
		$this->oServer->on('beforeCreateFile', array($this, 'beforeCreateFile'), 30);
		$this->oServer->on('afterCreateFile', array($this, 'afterCreateFile'), 30);
    }

    /**
     * Returns a plugin name.
     *
     * Using this name other plugins will be able to access other plugins
     * using \Sabre\DAV\Server::getPlugin
     *
     * @return string
     */
    public function getPluginName()
    {
        return 'contacts';
    }
	
	protected function getContact($iUserId, $sStorage, $sUID)
	{
		$mResult = false;
		$oEavManager = new \Aurora\System\Managers\Eav();
		$aEntities = $oEavManager->getEntities(
			'Aurora\\Modules\\Contacts\\Classes\\Contact', 
			[], 
			0, 
			1,
			[
				'IdUser' => $iUserId,
				'Storage' => $sStorage,
				'DavContacts::UID' => $sUID
			]
		);
		if (is_array($aEntities) && count($aEntities) > 0)
		{
			$mResult = $aEntities[0];
		}
		
		return $mResult;
	}
	
	protected function getStorage($sStorage)
	{
		$sResult = 'personal';
		if ($sStorage === \Afterlogic\DAV\Constants::ADDRESSBOOK_DEFAULT_NAME)
		{
			$sResult = 'personal';
		}
		else if ($sStorage === \Afterlogic\DAV\Constants::ADDRESSBOOK_SHARED_WITH_ALL_NAME)
		{
			$sResult = 'shared';
		}
		else if ($sStorage === \Afterlogic\DAV\Constants::ADDRESSBOOK_COLLECTED_NAME)
		{
			$sResult = 'collected';
		}
		
		return $sResult;
	}

    /**
     * @param string $sPath
     * @throws \Sabre\DAV\Exception\NotAuthenticated
     * @return bool
     */
    public function beforeUnbind($sPath)
    {
		return true;
	}    
	
	/**
     * @param string $sPath
     * @throws \Sabre\DAV\Exception\NotAuthenticated
     * @return bool
     */
    public function afterUnbind($sPath)
    {
		if ($this->oContactsDecorator) 
		{
			$iUserId = 0;
			$sUserPublicId = $this->oServer->getUser();
			
			$oCoreDecorator = \Aurora\System\Api::GetModuleDecorator('Core');
			if ($oCoreDecorator)
			{
				$oUser = $oCoreDecorator->GetUserByPublicId($sUserPublicId);
				if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
				{
					$iUserId = $oUser->EntityId;
				}
			}
			
			if ($iUserId > 0) 
			{
				$aPathInfo = pathinfo($sPath);
				\Aurora\System\Api::setUserId($iUserId);

				$sStorage = $this->getStorage(basename($aPathInfo['dirname']));
				$oContact = $this->getContact($iUserId, $sStorage, $aPathInfo['filename']);
				if ($oContact)
				{
					$this->oContactsDecorator->DeleteContacts(
						array($oContact->UUID)
					);
				}
			}
		}
		return true;
	}
	
	function beforeCreateFile($path, &$data, \Sabre\DAV\ICollection $parent, &$modified) {
		
		if ($parent->childExists(basename($path)))
		{
            throw new \Sabre\DAV\Exception\Conflict();
			
			return false;
		}

	}	
	
	function afterCreateFile($sPath, \Sabre\DAV\ICollection $oParent)
	{
		$aPathInfo = pathinfo($sPath);
		$sUUID = $aPathInfo['filename'];
		$oNode = $oParent->getChild($aPathInfo['basename']);
		
		$sStorage = $this->getStorage($oParent->getName());
		
		if ($oNode instanceof \Sabre\CardDAV\ICard && $this->oContactsDecorator) 
		{
			$iUserId = 0;
			$sUserPublicId = $this->oServer->getUser();
			$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
			if ($oCoreDecorator)
			{
				$oUser = $oCoreDecorator->GetUserByPublicId($sUserPublicId);
				if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
				{
					$iUserId = $oUser->EntityId;
				}
			}
			
			if ($iUserId > 0) 
			{
				\Aurora\System\Api::setUserId($iUserId);
				$oContactDb = $this->getContact($iUserId, $sStorage, $sUUID);
				if ($oContactDb)
				{
					$this->oDavContactsDecorator->UpdateContact($iUserId, $oNode->get(), $sUUID, $sStorage);
				}
				else
				{
					$this->oDavContactsDecorator->CreateContact($iUserId, $oNode->get(), $sUUID, $sStorage);
				}
			}
		}
	}

	function afterWriteContent($sPath, \Sabre\DAV\IFile $oNode)
	{
		if ($oNode instanceof \Sabre\CardDAV\ICard && $this->oContactsDecorator) 
		{
			$iUserId = 0;
			$sUserPublicId = $this->oServer->getUser();
			
			$oCoreDecorator = \Aurora\Modules\Core\Module::Decorator();
			if ($oCoreDecorator)
			{
				$oUser = $oCoreDecorator->GetUserByPublicId($sUserPublicId);
				if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
				{
					$iUserId = $oUser->EntityId;
				}
			}
			
			if ($iUserId > 0) 
			{
				\Aurora\System\Api::setUserId($iUserId);

				$sPath = $oNode->getName();
				$aPathInfo = pathinfo($sPath);
				$sUUID = $aPathInfo['filename'];
				
				$sStorage = $this->getStorage(basename($aPathInfo['dirname']));
				$oContactDb = $this->getContact($iUserId, $sStorage, $sUUID);
				\Aurora\System\Api::LogObject($oContactDb, \Aurora\System\Enums\LogLevel::Full, 'eee-');

				if (!isset($oContactDb)) 
				{
					$oVCard = \Sabre\VObject\Reader::read(
						$oNode->get(), 
						\Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
					);

					if ($oVCard && $oVCard->UID)
					{
						$sUUID = (string) $oVCard->UID;
						$oContactDb = $this->getContact($iUserId, $sStorage, $sUUID);
					}
				}

				if (isset($oContactDb)) 
				{
					$this->oDavContactsDecorator->UpdateContact($iUserId, $oNode->get(), $sUUID, $oContactDb->Storage);
				} 
				else 
				{
					$this->oDavContactsDecorator->CreateContact($iUserId, $oNode->get(), $sUUID);
				}
			}
		}
	}

}
