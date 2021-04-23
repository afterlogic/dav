<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS;

use Afterlogic\DAV\Server;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
trait NodeTrait
{
	/**
	 *
	 * @var [string]
	 */
	protected $rootPath = null;

	/**
	 * @var string $storage
	 */
	protected $storage = null;

	/**
	 * @var string $UserPublicId
	 */
	protected $UserPublicId = null;

	/**
	 *
	 * @var integer
	 */
	protected $access = Permission::Write;

	public function getStorage()
	{
    	return $this->storage;
	}

	public function getRootPath()
	{
		if ($this->rootPath === null)
		{
			list(, $owner) = \Sabre\Uri\split($this->getOwner());
			Server::getInstance()->setUser($owner);
			$oNode = Server::getInstance()->tree->getNodeForPath('files/'. $this->getStorage());

			if ($oNode)
			{
				$this->rootPath = $oNode->getPath();
			}
		}
		return $this->rootPath;
    }

	public function getRelativePath()
	{
        list($dir) = \Sabre\Uri\split($this->getPath());

		return \str_replace(
            $this->getRootPath(),
            '',
            $dir
        );
    }

	public function deleteShares()
	{
		$oSharedFilesModule = \Aurora\System\Api::GetModule('SharedFiles');
		if ($oSharedFilesModule && !$oSharedFilesModule->getConfig('Disabled'))
		{
			$pdo = new Backend\PDO();
			$pdo->deleteSharedFile(
				$this->getOwner(),
				$this->getStorage(),
				$this->getRelativePath() . '/' . $this->getName()
			);
		}
	}

	public function checkFileName($name)
	{
		if (strlen(trim($name)) === 0) throw new \Sabre\DAV\Exception\Forbidden('Permission denied to emty item');

        $path = $this->path . '/' . trim($name, '/');

        if (!file_exists($path)) throw new \Sabre\DAV\Exception\NotFound('File could not be located');
        if ($name == '.' || $name == '..') throw new \Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');

		return $path;
	}

    public function getDisplayName()
	{
		return $this->getName();
	}

    public function getId()
	{
		return $this->getName();
    }

    public function getPath()
    {
        return $this->path;
    }

	public function setPath($path)
	{
		$this->path = $path;
	}

    public function getOwner()
	{
		return \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $this->getUser();
	}

	public function getUser()
	{
        if ($this->UserPublicId === null)
        {
			$this->setUser(\Afterlogic\DAV\Server::getUser());
		}
		return $this->UserPublicId;
	}

	public function setUser($sUserPublicId)
	{
		$this->UserPublicId = $sUserPublicId;
	}

    /**
     * Returns a group principal.
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
	public function getGroup()
	{
		return null;
	}

    /**
     * Returns a list of ACE's for this node.
     *
     * Each ACE has the following properties:
     *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
     *     currently the only supported privileges
     *   * 'principal', a url to the principal who owns the node
     *   * 'protected' (optional), indicating that this ACE is not allowed to
     *      be updated.
     *
     * @return array
     */
	public function getACL()
	{
		$acl = [
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->getOwner(),
                'protected' => true,
            ],
        ];

        if ($this->access === Permission::Write) {
            $acl[] = [
                'privilege' => '{DAV:}write',
                'principal' => $this->getOwner(),
                'protected' => true,
            ];
        }

        return $acl;

	}

    /**
     * Updates the ACL.
     *
     * This method will receive a list of new ACE's as an array argument.
     *
     * @param array $acl
     */
	public function setACL(array $acl)
	{

	}

    /**
     * Returns the list of supported privileges for this node.
     *
     * The returned data structure is a list of nested privileges.
     * See Sabre\DAVACL\Plugin::getDefaultSupportedPrivilegeSet for a simple
     * standard structure.
     *
     * If null is returned from this method, the default privilege set is used,
     * which is fine for most common usecases.
     *
     * @return array|null
     */
	public function getSupportedPrivilegeSet()
	{
		return null;
	}

    public function getAccess()
    {
        return $this->access;
	}

	public function setAccess($access)
	{
		$this->access = $access;
	}

	/**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name)
    {
        list($parentPath, $oldName) = \Sabre\Uri\split($this->path);
        list(, $newName) = \Sabre\Uri\split($name);
        $newPath = $parentPath . '/' . $newName;

		$sRelativePath = $this->getRelativePath();

		$oldPathForShare = $sRelativePath . '/' .$oldName;
		$newPathForShare = $sRelativePath . '/' .$newName;

        $oSharedFiles = \Aurora\System\Api::GetModule('SharedFiles');
        if ($oSharedFiles && !$oSharedFiles->getConfig('Disabled', false))
        {
            $pdo = new Backend\PDO();
            $pdo->updateShare($this->getOwner(), $this->getStorage(), $oldPathForShare, $newPathForShare);
        }

        // We're deleting the existing resourcedata, and recreating it
        // for the new path.
        $resourceData = $this->getResourceData();
        $this->deleteResourceData();

		$oHistoryNode = null;
		if ($this instanceof File)
		{
			$oHistoryNode = $this->getHistoryDirectory();
		}

        rename($this->path, $newPath);
        $this->path = $newPath;
        $this->putResourceData($resourceData);

		if ($oHistoryNode instanceof Directory)
		{
			$oHistoryNode->setName($name . '.hist');
		}
    }
}
