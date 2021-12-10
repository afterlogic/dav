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
trait NodeTrait
{
	public $inRoot;
	
    protected $name;

    protected $node;

    protected $ownerPublicId = null;

    protected $relativeNodePath = null;

    protected $sharePath = '';

    protected $isInherited = false;
    
    public function getId()
    {
        return $this->getName();
    }

    public function setOwnerPublicId($sOwnerPublicId)
    {
        $this->ownerPublicId = $sOwnerPublicId;
    }

    public function getOwnerPublicId()
    {
        return $this->ownerPublicId;
    }

    public function setRelativeNodePath($sPath)
    {
        $this->relativeNodePath = $sPath;
    }

    public function getRelativeNodePath()
    {
        return $this->relativeNodePath;
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
        return $this->name;
    }

    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name)
    {
        if ($this->isInherited) {
            $this->node->setName($name);
        } else {
            $pdo = new \Afterlogic\DAV\FS\Backend\PDO();
            $oNode = $pdo->getSharedFileByUidWithPath($this->getOwner(), $name, $this->getSharePath());
            if ($oNode) {
                throw new \Sabre\DAV\Exception\Conflict();
            }
    
            $pdo->updateSharedFileName($this->getOwner(), $this->name, $name, $this->getSharePath());
        }
    }

    function delete()
    {
        if ($this->isInherited) {
            $this->node->delete();
        } else {
            $pdo = new \Afterlogic\DAV\FS\Backend\PDO();
            return $pdo->deleteShare($this->getOwner(), $this->getId(), $this->getSharePath());
        }
    }

    public function setSharePath($sharePath)
    {
        $this->sharePath = $sharePath;
    }

    public function getSharePath()
    {
        return $this->sharePath;
    }

    public function setInherited($bIsInherited)
    {
        $this->isInherited = $bIsInherited;
    }

    public function isInherited()
    {
        return $this->isInherited;
    }

    public function getRelativePath()
    {
        return $this->getRelativeNodePath();
    }

    public function getNode()
    {
        return $this->node;
    }
}
