<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Shared;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class File extends \Afterlogic\DAV\FS\File implements \Sabre\DAVACL\IACL
{
    use PropertyStorageTrait;

    protected $node;

    protected $relativeNodePath = null;

    protected $ownerPublicId = null;

    public function __construct($node)
    {
        $this->node = $node;
    }

    public function setRelativeNodePath($sPath)
    {
        $this->relativeNodePath = $sPath;
    }

    public function getRelativeNodePath()
    {
        return $this->relativeNodePath;
    }

    public function setOwnerPublicId($sOwnerPublicId)
    {
        $this->ownerPublicId = $sOwnerPublicId;
    }

    public function getOwnerPublicId()
    {
        return $this->ownerPublicId;
    }

    public function getStorage()
    {
        return $this->node->getStorage();
    }

    public function getRootPath()
    {
        return $this->node->getRootPath();
    }

    public function getPath()
    {
        return $this->node->getPath();
    }

    // public function getOwner()
    // {
    //     return $this->principalUri;
    // }

    public function getAccess()
    {
//        return \Aurora\Modules\SharedFiles\Enums\Access::Read;
        return $this->node->getAccess();
    }

    public function getName()
    {
        return $this->node->getName();
    }

    public function getId()
    {
        return $this->getName();
    }

    public function getDisplayName()
	{
        return $this->getName();
	}

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getLastModified()
    {
        return $this->node->getLastModified();
    }

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getSize()
    {
        return $this->node->getSize();
    }

    function get($bRedirectToUrl = true)
    {
        return $this->node->get($bRedirectToUrl);
    }

    function delete()
    {
            $pdo = new \Afterlogic\DAV\FS\Backend\PDO();
            $pdo->deleteShare($this->principalUri, $this->getId());
    }

    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name)
    {
        throw new \Sabre\DAV\Exception\Conflict();
    }

    public function put($data)
    {
        return $this->node->put($data);
    }

    public function getRelativePath()
    {
        return $this->getRelativeNodePath();
    }
}
