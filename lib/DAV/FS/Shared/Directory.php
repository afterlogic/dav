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
class Directory extends \Afterlogic\DAV\FS\Directory
{
    use PropertyStorageTrait;

    protected $name;

    protected $node;

    protected $relativeNodePath = null;

    protected $ownerPublicId = null;

    protected $sharePath = '';

    public function __construct($name, $node)
    {
        $this->name = $name;
        $this->node = $node;
        $this->setAccess($node->getAccess());
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

    public function getName()
    {
//        return $this->node->getName();
        return $this->name;
    }

    public function getDisplayName()
	{
        return $this->getName();
	}

    public function getId()
    {
        return $this->getName();
    }

    public function getChild($path)
    {
        $oChild = $this->node->getChild($path);
        if ($oChild)
        {
            $oChild->setAccess($this->getAccess());
        }

        return $oChild;
    }

    public function getChildren()
    {
        $aChildren = $this->node->getChildren();
        foreach ($aChildren as $oChild)
        {
            $oChild->setAccess($this->getAccess());
        }
        return $aChildren;
    }

    function delete()
    {
        $pdo = new \Afterlogic\DAV\FS\Backend\PDO();
        $pdo->deleteShare($this->getUser(), $this->getId());
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

    public function childExists($name)
    {
        return $this->node->childExists($name);
    }

	public function createDirectory($name)
	{
        $this->node->createDirectory($name);
    }

	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = [])
	{
        return $this->node->createFile($name, $data, $rangeType, $offset, $extendedProps);
    }

    public function getRelativePath()
    {
        return $this->getRelativeNodePath();
    }

    public function setSharePath($sharePath)
    {
        $this->sharePath;
    }

    public function getSharePath()
    {
        return $this->sharePath;
    }
}
