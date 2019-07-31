<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\Contacts;

use Aurora\System\Managers\Eav;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
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
	
	public static function isContact($uri)
	{
		$sUriExt = pathinfo($uri, PATHINFO_EXTENSION);
		return ($sUriExt != null && strtoupper($sUriExt) == 'VCF');
	}
	
	protected function getContactFromDB($iUserId, $sStorage, $sUID)
	{
		$mResult = false;
		$aEntities = Eav::getInstance()->getEntities(
			\Aurora\Modules\Contacts\Classes\Contact::class, 
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
	
	protected function getGroupFromDB($iUserId, $sUID)
	{
		$mResult = false;
		$aEntities = Eav::getInstance()->getEntities(\Aurora\Modules\Contacts\Classes\Group::class, 
			[], 
			0, 
			1,
			[
				'IdUser' => $iUserId,
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
		if ($this->oContactsDecorator && self::isContact($sPath)) 
		{
			$iUserId = 0;
			$sUserPublicId = \Afterlogic\DAV\Server::getUser();

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
				$oContact = $this->getContactFromDB($iUserId, $sStorage, $aPathInfo['filename']);
				if ($oContact)
				{
					$this->oContactsDecorator->DeleteContacts(
						$sStorage,
						array($oContact->UUID)
					);
				}
				else
				{
					$oGroup = $this->getGroupFromDB($iUserId, $aPathInfo['filename']);
					if ($oGroup)
					{
						$this->oContactsDecorator->DeleteGroup(
							$oGroup->UUID
						);
					}
				}
			}
		}
		return true;
	}
	
	function beforeCreateFile($path, &$data, \Sabre\DAV\ICollection $parent, &$modified) {
		
		if (self::isContact($path))
		{
			if ($parent->childExists(basename($path)))
			{
				throw new \Sabre\DAV\Exception\Conflict();
				
				return false;
			}
		}
	}	
	
	function afterCreateFile($sPath, \Sabre\DAV\ICollection $oParent)
	{
		if (self::isContact($sPath))
		{
			$aPathInfo = pathinfo($sPath);
			$oNode = $oParent->getChild($aPathInfo['basename']);
			$sStorage = $this->getStorage($oParent->getName());

			$this->updateOrCreateContactItem($sPath, $oNode, $sStorage);
		}
	}

	function afterWriteContent($sPath, \Sabre\DAV\IFile $oNode)
	{
		$aPathInfo = pathinfo($sPath);
		$sStorage = $this->getStorage(basename($aPathInfo['dirname']));
		$this->updateOrCreateContactItem($sPath, $oNode, $sStorage);
	}

	protected function updateOrCreateContactItem($sPath, \Sabre\DAV\IFile $oNode, $sStorage)
	{
		$aPathInfo = pathinfo($sPath);
		
		if ($oNode instanceof \Sabre\CardDAV\ICard && $this->oContactsDecorator) 
		{
			$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId(
				\Afterlogic\DAV\Server::getUser()
			);

			$iUserId = 0;
			if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
			{
				$iUserId = $oUser->EntityId;
			}
			
			if ($iUserId > 0) 
			{
				\Aurora\System\Api::setUserId($iUserId);

				$sFileName = $aPathInfo['filename'];

				$oContactDb = $this->getContactFromDB($iUserId, $sStorage, $sFileName);
				if ($oContactDb)
				{
					$this->oDavContactsDecorator->UpdateContact($iUserId, $oNode->get(), $sFileName, $sStorage);
				}
				else
				{
					$oGroupDb = $this->getGroupFromDB($iUserId, $sFileName);
					if ($oGroupDb)
					{
						$this->oDavContactsDecorator->UpdateGroup($iUserId, $oNode->get(), $sFileName);
					}
					else 
					{
						$sData = $oNode->get();
						$oVCard = \Sabre\VObject\Reader::read(
							$sData, 
							\Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
						);
						if ($oVCard && $oVCard->UID)
						{
							$oVCard = $oVCard->convert(\Sabre\VObject\Document::VCARD40);
//							$sUUID = \str_replace('urn:uuid:', '', (string) $oVCard->UID);

							$sUUID = $sFileName;

							$bIsGroup = (isset($oVCard->KIND) && strtoupper((string) $oVCard->KIND) === 'GROUP');
							if ($bIsGroup)
							{
								$oGroupDb = $this->getGroupFromDB($iUserId, $sUUID);
								if ($oGroupDb)
								{
									$this->oDavContactsDecorator->UpdateGroup($iUserId, $sData, $sUUID);
								}
								else 
								{
									$this->oDavContactsDecorator->CreateGroup($iUserId, $sData, $sUUID);
								}
							}
							else
							{
								$oContactDb = $this->getContactFromDB($iUserId, $sStorage, $sUUID);
								if ($oContactDb)
								{
									$this->oDavContactsDecorator->UpdateContact($iUserId, $sData, $sUUID, $sStorage);
								}
								else 
								{
									$this->oDavContactsDecorator->CreateContact($iUserId, $sData, $sUUID, $sStorage);
								}
							}
						}
					}
				}
			}
		}
	}

}
