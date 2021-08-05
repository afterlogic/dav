<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\Contacts;

use Aurora\Modules\Contacts\Models\Contact;
use Aurora\Modules\Contacts\Models\Group;

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
		if ($oUser instanceof \Aurora\Modules\Core\Models\User)
		{
			$iUserId = $oUser->Id;
		}

		return $iUserId;
	}

	protected function getContactFromDB($iUserId, $sStorage, $sUID)
	{
		return Contact::where('IdUser', $iUserId)
			->where('Storage', $sStorage)
			->where('Properties->DavContacts::UID', $sUID)
			->first();
	}

	protected function getGroupFromDB($iUserId, $sUID)
	{
		return Group::where('IdUser', $iUserId)
			->where('Properties->DavContacts::UID', $sUID)
			->first();
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
			$iUserId = $this->getCurrentUserId();
			if ($iUserId > 0)
			{
				\Aurora\System\Api::setUserId($iUserId);

				$sStorage = $this->getStorage($sPath);
				if ($sStorage === 'collected')
				{
					$sStorage = 'personal';
				}

				$sUID = $this->getUID($sPath);
				$oContact = $this->getContactFromDB($iUserId, $sStorage, $sUID);
				if ($oContact)
				{
					$this->oContactsDecorator->DeleteContacts(
						$iUserId,
						$sStorage,
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
			$sStorage = $this->getStorage($sPath);

			$this->updateOrCreateContactItem($sPath, $oNode, $sStorage);
		}
	}

	function afterWriteContent($sPath, \Sabre\DAV\IFile $oNode)
	{
		if ($oNode instanceof \Sabre\CardDAV\ICard)
		{
			$sStorage = $this->getStorage($sPath);

			$this->updateOrCreateContactItem($sPath, $oNode, $sStorage);
		}
	}

	protected function updateOrCreateContactItem($sPath, \Sabre\DAV\IFile $oNode, $sStorage)
	{
		if ($oNode instanceof \Sabre\CardDAV\ICard && $this->oContactsDecorator)
		{
			$iUserId = $this->getCurrentUserId();

			if ($iUserId > 0)
			{
				\Aurora\System\Api::setUserId($iUserId);

				$sData = $oNode->get();

				$sUID = $this->getUID($sPath);
				if ($this->getContactFromDB($iUserId, $sStorage, $sUID))
				{
					$this->oDavContactsDecorator->UpdateContact($iUserId, $sData, $sUID, $sStorage);
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
									$this->oDavContactsDecorator->UpdateContact($iUserId, $sData, $sUID, $sStorage);
								}
								else
								{
									$this->oDavContactsDecorator->CreateContact($iUserId, $sData, $sUID, $sStorage);
								}
							}
						}
					}
				}
			}
		}
	}

}
