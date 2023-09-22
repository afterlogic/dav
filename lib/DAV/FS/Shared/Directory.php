<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Shared;

use Afterlogic\DAV\Constants;
use Afterlogic\DAV\Server;
use Aurora\Modules\Files\Enums\ErrorCodes;
use Aurora\Modules\SharedFiles\Enums\Access;
use Aurora\System\Exceptions\ApiException;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Directory extends \Afterlogic\DAV\FS\Directory
{
    use PropertyStorageTrait;
    use NodeTrait;

    public function __construct($name, $node)
    {
        $this->name = $name;
        $this->node = $node;
    }

    public function getChild($path)
    {
        if ($this->node) {
            $oChild = $this->node->getChild($path);
            if ($oChild) {
                $oChild->setUser($this->node->getUser());
                $aExtendedProps = $oChild->getProperty('ExtendedProps');
                if (!(is_array($aExtendedProps) && isset($aExtendedProps['InitializationVector']))) {
                    if ($oChild instanceof \Afterlogic\DAV\FS\File) {
                        $oChild = new File($oChild->getName(), $oChild);
                    } elseif ($oChild instanceof \Afterlogic\DAV\FS\Directory) {
                        $oChild = new Directory($oChild->getName(), $oChild);
                    }
                    $oPdo = new \Afterlogic\DAV\FS\Backend\PDO();
                    $aSharedFile = $oPdo->getSharedFile(Constants::PRINCIPALS_PREFIX . $this->getUser(), $oChild->getNode()->getRelativePath() . '/' . $oChild->getNode()->getName());
                    
                    $oChild->setInherited(true);
                    if ($aSharedFile) {
                        $oChild->setOwnerPublicId($this->node->getUser());
                        $oChild->setInitiator($aSharedFile['initiator']);
                        $oChild->setAccess($aSharedFile['access']);
                        if (isset($aSharedFile['properties'])) {
                            $oChild->setDbProperties(\json_decode($aSharedFile['properties'], true));
                        }
                    } else {
                        $oChild->setAccess($this->getAccess());
                        $oChild->setOwnerPublicId($this->node->getUser());
                    }
                } else {
                    return false;
                }

                return $oChild;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function getChildren()
    {
        $aResult = [];
        if ($this->node) {
            $this->node->setUser($this->getOwnerPublicId());
            $aChildren = $this->node->getChildren();
            foreach ($aChildren as $oChild) {
                $oResult = false;
                if ($oChild instanceof \Afterlogic\DAV\FS\File) {
                    $oChild->setUser($this->getOwnerPublicId());
                    $aExtendedProps = $oChild->getProperty('ExtendedProps');
                    if (!(is_array($aExtendedProps) && isset($aExtendedProps['InitializationVector']))) {
                        $oResult = new File($oChild->getName(), $oChild);
                    }
                } elseif ($oChild instanceof \Afterlogic\DAV\FS\Directory) {
                    $oResult = new Directory($oChild->getName(), $oChild);
                }
                if ($oResult) {
                    $oPdo = new \Afterlogic\DAV\FS\Backend\PDO();
                    $aSharedFile = $oPdo->getSharedFile(Constants::PRINCIPALS_PREFIX . $this->getUser(), $oResult->getNode()->getRelativePath() . '/' . $oResult->getNode()->getName());
                    
                    $oResult->setInherited(true);
                    if ($aSharedFile) {
                        $oResult->setAccess($aSharedFile['access']);
                        $oResult->setInitiator($aSharedFile['initiator']);
                        if (isset($aSharedFile['properties'])) {
                            $oResult->setDbProperties(\json_decode($aSharedFile['properties'], true));
                        }
                    } else {
                        $oResult->setOwnerPublicId($this->node->getUser());
                        $oResult->setAccess($this->node->getAccess());
                    }
                    if ($oResult->getAccess() !== Access::NoAccess) {
                        $aResult[] = $oResult;
                    }
                }
            }
        }

        return $aResult;
    }

    public function childExists($name)
    {
        return $this->node && $this->node->childExists($name);
    }

    public function createDirectory($name)
    {
        if ($this->node) {
            if ($this->node->getAccess() === Access::NoAccess || $this->node->getAccess() === Access::Read) {
                throw new ApiException(ErrorCodes::NotPermitted);
            }
            $this->node->createDirectory($name);
        }
    }

    public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = [])
    {
        if ($this->node) {
            if ($this->node->getAccess() === Access::NoAccess || $this->node->getAccess() === Access::Read) {
                throw new ApiException(ErrorCodes::NotPermitted);
            }
            if ($this->node->childExists($name)) {
                $oFile = $this->node->getChild($name);
                if ($oFile->getAccess() === Access::NoAccess || $oFile->getAccess() === Access::Read) {
                    throw new ApiException(ErrorCodes::NotPermitted);
                }
            }

            if (!(is_array($extendedProps) && isset($extendedProps['InitializationVector']))) {
                return $this->node->createFile($name, $data, $rangeType, $offset, $extendedProps);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    public function getLastModified()
    {
        if ($this->node) {
            return $this->node->getLastModified();
        }

        return 0;
    }
}
