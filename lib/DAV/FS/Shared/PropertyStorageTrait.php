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
trait PropertyStorageTrait
{
    public function getResourceInfoPath()
    {
        if ($this->node) {
            return $this->node->getResourceInfoPath();
        } else {
            return false;
        }
    }

    public function getProperty($sName)
    {
        if ($this->node) {
            return $this->node->getProperty($sName);
        } else {
            return false;
        }
    }

    public function setProperty($sName, $mValue)
    {
        if ($this->node) {
            $this->node->setProperty($sName, $mValue);
        }
    }

    public function updateProperties($properties)
    {
        if ($this->node) {
            return $this->node->updateProperties($properties);
        } else {
            return false;
        }
    }

    public function getProperties($properties)
    {
        if ($this->node) {
            return $this->node->getProperties($properties);
        } else {
            return [];
        }
    }

    public function getResourceData()
    {
        if ($this->node) {
            return $this->node->getResourceData();
        } else {
            return ['properties' => []];
        }
    }

    public function putResourceData(array $newData)
    {
        if ($this->node) {
            $this->node->putResourceData($newData);
        }
    }

    public function deleteResourceData()
    {
        if ($this->node) {
            $this->node->deleteResourceData();
        }
    }
}
