<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Shared;

use Afterlogic\DAV\Constants;
use Afterlogic\DAV\Server;
use Aurora\Modules\SharedFiles\Enums\Access;
use LogicException;

use function Sabre\Uri\split;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Root extends \Afterlogic\DAV\FS\Directory implements \Sabre\DAVACL\IACL {

	use \Afterlogic\DAV\FS\Shared\DirectoryTrait;

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
				$oItem->setUser(basename($aSharedFile['owner']));
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
				$mResult->setInitiator($aSharedFile['initiator']);
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

	protected function populateAccess(&$oChild, $aSharedFile) 
	{
		// NoAccess = 0;
		// Write	 = 1;
		// Read   = 2;
		// Reshare = 3;

		if ((int) $aSharedFile['group_id'] === 0) {
			
			$oChild->setAccess($aSharedFile['access']);
		} else {

			$iAccess = $oChild->getAccess();
			if ($aSharedFile['access'] !== Access::Read) {

				if ($iAccess < $aSharedFile['access'] ) {
					
					$oChild->setAccess($aSharedFile['access']);
				}
			} elseif ($iAccess !== Access::Write || $iAccess !== Access::Reshare) {
					
				$oChild->setAccess($aSharedFile['access']);
			}
		}
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



		$aSharedFiles = $this->pdo->getSharedFilesByUid(
			Constants::PRINCIPALS_PREFIX . $this->getUser(), 
			$new_name
		);

		foreach ($aSharedFiles as $aSharedFile) {
			if (is_array($aSharedFile)) {
				if ($aSharedFile['owner'] !== $aSharedFile['principaluri']) {

					if (self::hasHistoryDirectory($name)) {
						$aSharedFile['path'] = $aSharedFile['path'] . '.hist';
						$aSharedFile['uid'] = $aSharedFile['uid'] . '.hist';
					}

					if ($oChild) {
						$sPath = '/' . ltrim($oChild->getRelativePath() . '/' . $oChild->getName(), '/');
						if ($aSharedFile['path'] === $sPath) {

							$this->populateAccess($oChild, $aSharedFile);
						}
					} else {
						$oChild = Root::populateItem($aSharedFile);
					}
				}
			}
			else {
				$aSharedFile = $this->pdo->getSharedFileBySharePath(
					Constants::PRINCIPALS_PREFIX . Server::getUser(), 
					'/' . trim($name, '/')
				);
				if ($aSharedFile) {
					$oChild = Server::getNodeForPath('files/personal/' . trim($name, '/'));
				}
			}
		}

		return $oChild;
    }

	public function getChildren()
	{
		return $this->getSharedChildren();
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
