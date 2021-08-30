<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\Contacts;

use Aurora\Modules\Contacts\Classes\AddressBook;
use Aurora\System\EAV\Query;
use Aurora\System\Managers\Eav;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

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
		$this->oServer->on('beforeBind', array($this, 'beforeBind'),30);
		$this->oServer->on('beforeUnbind', array($this, 'beforeUnbind'),30);
        $this->oServer->on('afterUnbind', array($this, 'afterUnbind'),30);
		$this->oServer->on('afterWriteContent', array($this, 'afterWriteContent'), 30);
		$this->oServer->on('beforeCreateFile', array($this, 'beforeCreateFile'), 30);
		$this->oServer->on('afterCreateFile', array($this, 'afterCreateFile'), 30);

		$this->oServer->on('afterMethod', array($this, 'afterMethod'),30);
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

	protected function getStorage($uri)
	{
		$sResult = 'personal';

		$aPathInfo = \pathinfo($uri);
		$sStorage = \basename($aPathInfo['dirname']);

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
		else
		{
			$sResult = 'addressbook';
		}

		return $sResult;
	}

	public function getUID($uri)
	{
		$aPathInfo = \pathinfo($uri);
		return \basename($aPathInfo['filename']);
	}

	public static function isAddressbooks($uri)
	{
		$aPathInfo = \pathinfo($uri);
		return \strtolower(\dirname($aPathInfo['dirname'])) === 'addressbooks';
	}

	public static function isContact($uri)
	{
		$sUriExt = \pathinfo($uri, PATHINFO_EXTENSION);
		return ($sUriExt != null && strtoupper($sUriExt) == 'VCF');
	}

	protected function getCurrentUserId()
	{
		$iUserId = 0;

		$oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId(
			\Afterlogic\DAV\Server::getUser()
		);
		if ($oUser instanceof \Aurora\Modules\Core\Classes\User)
		{
			$iUserId = $oUser->EntityId;
		}

		return $iUserId;
	}

	protected function getContactFromDB($iUserId, $sStorage, $sUID)
	{
		return (new \Aurora\System\EAV\Query())
			->select()
			->whereType(\Aurora\Modules\Contacts\Classes\Contact::class)
			->where([
				'IdUser' => $iUserId,
				'Storage' => $sStorage,
				'DavContacts::UID' => $sUID
			])
			->offset(0)
			->limit(1)
			->one()
			->exec();
	}

	protected function getGroupFromDB($iUserId, $sUID)
	{
		return (new \Aurora\System\EAV\Query())
			->select()
			->whereType(\Aurora\Modules\Contacts\Classes\Group::class)
			->where([
				'IdUser' => $iUserId,
				'DavContacts::UID' => $sUID
			])
			->offset(0)
			->limit(1)
			->one()
			->exec();
	}

	public function afterMethod(RequestInterface $request, ResponseInterface $response)
	{
		$aPostData = $request->getPostData();
		if (isset($aPostData['resourceType']) && 
			$aPostData['resourceType'] === "{DAV:}collection,{urn:ietf:params:xml:ns:carddav}addressbook" && 
			$aPostData['sabreAction'] === 'mkcol')
		{
			$oContactsModule = \Aurora\Modules\Contacts\Module::getInstance();
			$oContactsModule->CreateAddressBook($aPostData['{DAV:}displayname'], $this->getCurrentUserId(), $aPostData['name']);
		}
	}

	public function beforeBind($sPath)
	{
		$aPathInfo = \pathinfo($sPath);
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
			if (self::isContact($sPath))
			{
				$iUserId = $this->getCurrentUserId();
				if ($iUserId > 0)
				{
					\Aurora\System\Api::setUserId($iUserId);

					$sStorage = $this->getStorage($sPath);
					if ($sStorage === 'collected')
					{
						$sStorage = 'personal';
					}

					$sContactStorage = $sStorage;
					if ($sStorage === 'addressbook') {
						$aPathInfo = \pathinfo($sPath);
						$oEavAddressBook = (new Query(AddressBook::class))->where([
							'UUID' => \basename($aPathInfo['dirname']),
							'IdUser' => $iUserId
						])->one()->exec();
						if ($oEavAddressBook)
						{
							$sContactStorage = 'addressbook' . $oEavAddressBook->EntityId;
						}
					}

					$sUID = $this->getUID($sPath);
					$oContact = $this->getContactFromDB($iUserId, $sStorage, $sUID);
					if ($oContact)
					{
						$this->oContactsDecorator->DeleteContacts(
							$iUserId,
							$sContactStorage,
							[$oContact->UUID]
						);
					}
					else
					{
						$oGroup = $this->getGroupFromDB($iUserId, $sUID);
						if ($oGroup)
						{
							$this->oContactsDecorator->DeleteGroup(
								$iUserId,
								$oGroup->UUID
							);
						}
					}
				}
			}
			else
			{
				$aPathInfo = \pathinfo($sPath);

				if (\strtolower($aPathInfo['dirname']) === 'addressbooks')
				{
					$oEavAddressBook = (new Query(AddressBook::class))->where([
						'UUID' => $aPathInfo['basename'],
						'IdUser' => $this->getCurrentUserId()
					])->one()->exec();
					if ($oEavAddressBook)
					{
						$oEavAddressBook->delete();
					}
				}
			}
		}
		return true;
	}

	function beforeCreateFile($path, &$data, \Sabre\DAV\ICollection $parent, &$modified) {

		if (self::isContact($path))
		{
			if ($parent->childExists(\basename($path)))
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

			$this->updateOrCreateContactItem($sPath, $oNode);
		}
	}

	function afterWriteContent($sPath, \Sabre\DAV\IFile $oNode)
	{
		if ($oNode instanceof \Sabre\CardDAV\ICard)
		{
			$this->updateOrCreateContactItem($sPath, $oNode);
		}
	}

	protected function updateOrCreateContactItem($sPath, \Sabre\DAV\IFile $oNode)
	{
		if ($oNode instanceof \Sabre\CardDAV\ICard && $this->oContactsDecorator)
		{
			$iUserId = $this->getCurrentUserId();

			if ($iUserId > 0)
			{
				\Aurora\System\Api::setUserId($iUserId);

				$sStorage = $this->getStorage($sPath);
				$sContactStorage = $sStorage;
				if ($sStorage === 'addressbook') {
					$aPathInfo = \pathinfo($sPath);
					$oEavAddressBook = (new Query(AddressBook::class))->where([
						'UUID' => \basename($aPathInfo['dirname']),
						'IdUser' => $iUserId
					])->one()->exec();
					if ($oEavAddressBook)
					{
						$sContactStorage = 'addressbook' . $oEavAddressBook->EntityId;
					}
				}

				$sData = $oNode->get();

				$sUID = $this->getUID($sPath);
				if ($this->getContactFromDB($iUserId, $sStorage, $sUID))
				{
					$this->oDavContactsDecorator->UpdateContact($iUserId, $sData, $sUID, $sContactStorage);
				}
				else
				{
					if ($this->getGroupFromDB($iUserId, $sUID))
					{
						$this->oDavContactsDecorator->UpdateGroup($iUserId, $sData, $sUID);
					}
					else
					{
						$oVCard = \Sabre\VObject\Reader::read(
							$sData,
							\Sabre\VObject\Reader::OPTION_IGNORE_INVALID_LINES
						);
						if ($oVCard && $oVCard->UID)
						{
							$oVCard = $oVCard->convert(\Sabre\VObject\Document::VCARD40);

							if (isset($oVCard->KIND) && strtoupper((string) $oVCard->KIND) === 'GROUP')
							{
								if ($this->getGroupFromDB($iUserId, $sUID))
								{
									$this->oDavContactsDecorator->UpdateGroup($iUserId, $sData, $sUID);
								}
								else
								{
									$this->oDavContactsDecorator->CreateGroup($iUserId, $sData, $sUID);
								}
							}
							else
							{
								if ($this->getContactFromDB($iUserId, $sStorage, $sUID))
								{
									$this->oDavContactsDecorator->UpdateContact($iUserId, $sData, $sUID, $sContactStorage);
								}
								else
								{
									$this->oDavContactsDecorator->CreateContact($iUserId, $sData, $sUID, $sContactStorage);
								}
							}
						}
					}
				}
			}
		}
	}

}
