<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Shared;

use Afterlogic\DAV\Constants;
use Afterlogic\DAV\Server;
use LogicException;

use function Sabre\Uri\split;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Root extends \Afterlogic\DAV\FS\Directory implements \Sabre\DAVACL\IACL {

//    use NodeTrait;

	protected $pdo = null;

	public function __construct() {

		$this->getUser();

		$this->pdo = new \Afterlogic\DAV\FS\Backend\PDO();
	}

	public function getName() {

        return \Aurora\System\Enums\FileStorageType::Shared;

	}

    /**
     * Returns the owner principal.
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
	public function getOwner()
	{
		return \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $this->UserPublicId;
	}

	public static function populateItem($aSharedFile)
	{
		$mResult = false;

		if (is_array($aSharedFile)) {

			$oServer = \Afterlogic\DAV\Server::createInstance();
			$sCurrentUser = $oServer->getUser();
			$oServer->setUser(basename($aSharedFile['owner']));
			$oItem = null;

			try {

				$oItem = $oServer->tree->getNodeForPath('files/' . $aSharedFile['storage'] . '/' .  trim($aSharedFile['path'], '/'));
			}
			catch (\Exception $oEx) {

				\Aurora\Api::LogException($oEx);
			}
			$oServer->setUser($sCurrentUser);

			if ($oItem instanceof \Sabre\DAV\FS\Node) {

				$oItem->setAccess((int) $aSharedFile['access']);
			}

			if (!$aSharedFile['isdir']) {

				$mResult = new File($aSharedFile['uid'], $oItem);
			}
			else if ($oItem instanceof \Afterlogic\DAV\FS\Directory) {

				$mResult = new Directory($aSharedFile['uid'], $oItem);
			}

			if ($mResult) {
				
				list($sRelativeNodePath, ) = split($aSharedFile['path']);
				if ($sRelativeNodePath === '/') {

					$sRelativeNodePath = '';
				}
				$mResult->setRelativeNodePath($sRelativeNodePath);
				$mResult->setOwnerPublicId(basename($aSharedFile['owner']));
				$mResult->setSharePath($aSharedFile['share_path']);
				$mResult->setAccess((int) $aSharedFile['access']);
				$mResult->setGroupId($aSharedFile['group_id']);
			}
		}
		return $mResult;
	}

	public static function hasHistoryDirectory(&$name)
	{
		$bHasHistory = false;
		if (substr($name, -5) === '.hist')
		{
			$bHasHistory = true;
			$name = substr($name, 0, strpos($name, '.hist'));
		}

		return $bHasHistory;
	}

	public function getChild($name)
	{
		$oChild = false;
		$new_name = $name;
		$pathinfo = pathinfo($new_name);
		if (isset($pathinfo['extension']) && $pathinfo['extension'] === 'hist') {
			$new_name = $pathinfo['filename'];
		}
		$aSharedFile = $this->pdo->getSharedFileByUid(
			\Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $this->UserPublicId, 
			$new_name
		);
		
		if (is_array($aSharedFile)) {
			if (self::hasHistoryDirectory($name)) {
				$aSharedFile['path'] = $aSharedFile['path'] . '.hist';
				$aSharedFile['uid'] = $aSharedFile['uid'] . '.hist';
			}

			$oChild = self::populateItem($aSharedFile);
		} else {
			$aSharedFile = $this->pdo->getSharedFileBySharePath(
				Constants::PRINCIPALS_PREFIX . Server::getUser(), 
				'/' . trim($name, '/')
			);
			if ($aSharedFile) {
				$oChild = Server::getNodeForPath('files/personal/' . trim($name, '/'));
			}
		}

		return $oChild;
    }

	public function getChildren()
	{
		$aResult = [];

		$aSharedFiles = $this->pdo->getSharedFilesForUser(
			Constants::PRINCIPALS_PREFIX . $this->getUser()
		);

		foreach ($aSharedFiles as $aSharedFile) {
			$oSharedItem = self::populateItem($aSharedFile);
			if ($oSharedItem) {
				$aResult[] = $oSharedItem;
			}
		}

		return $aResult;
	}

	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = [])
	{
		$oFile = $this->getChild($name);
		if ($oFile instanceof File)
		{
			$oFile->put($data);
		}
		else
		{
			throw new \Sabre\DAV\Exception\Forbidden();
		}
	}

}
