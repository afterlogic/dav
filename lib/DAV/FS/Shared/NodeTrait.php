<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Shared;

use Afterlogic\DAV\Constants;

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

    protected $groupId = null;

    protected $initiator = '';
    
    public function getId()
    {
        return $this->getName();
    }

    public function getDisplayName()
	{
        return $this->getName();
	}

    public function setOwnerPublicId($sOwnerPublicId)
    {
        $this->ownerPublicId = $sOwnerPublicId;
    }

    public  function getOwner()
    {
        return $this->getOwnerPublicId();
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
        if ($this->node) {
            return $this->node->getStorage();
        } else {
            return 'unknown';
        }
    }

    public function getRootPath()
    {
        if ($this->node) {
            return $this->node->getRootPath();
        } else {
            return '';
        }
    }

    public function getPath()
    {
        if ($this->node) {
            return $this->node->getPath();
        } else {
            return '';
        }
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
            if ($this->node) {
                $aExtendedProps = $this->node->getProperty('ExtendedProps');
                if (!(is_array($aExtendedProps) && isset($aExtendedProps['InitializationVector']))) {
                    $this->node->setName($name);
                }
            }
        } else {
            $pdo = new \Afterlogic\DAV\FS\Backend\PDO();
            $oNode = $pdo->getSharedFileByUidWithPath(Constants::PRINCIPALS_PREFIX . $this->getUser(), $name, $this->getSharePath());
            if ($oNode) {
                throw new \Sabre\DAV\Exception\Conflict();
            }
    
            $pdo->updateSharedFileName(Constants::PRINCIPALS_PREFIX . $this->getUser(), $this->name, $name, $this->getSharePath());
        }
    }

    function delete()
    {
        if ($this->isInherited) {
            if ($this->node) {
                $aExtendedProps = $this->node->getProperty('ExtendedProps');
                if (!(is_array($aExtendedProps) && isset($aExtendedProps['InitializationVector']))) {
                    $this->node->delete();
                }
            }
        } else {
            $pdo = new \Afterlogic\DAV\FS\Backend\PDO();
            return $pdo->deleteShare(Constants::PRINCIPALS_PREFIX . $this->getUser(), $this->getId(), $this->getSharePath());
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
        if ($this->isInherited()) {
            return $this->node->getRelativePath();
        } else {
            $sharePath = $this->getSharePath();
            if ($sharePath) {
                return $sharePath;
            } else {
                return '';
            }
        }
    }

    public function getNode()
    {
        return $this->node;
    }

    public function getGroupId()
    {
        return $this->groupId;
    }

    public function setGroupId($groupId)
    {
        $this->groupId = isset($groupId) ? (int) $groupId : 0;
    }

    public function getInitiator()
    {
        return $this->initiator;
    }

    public function setInitiator($initiator)
    {
        $this->initiator = $initiator;
    }
}
