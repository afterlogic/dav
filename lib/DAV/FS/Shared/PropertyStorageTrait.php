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
        $this->node->getResourceInfoPath();
    }

    public function getProperty($sName)
    {
        return $this->node->getProperty($sName);
    }

    public function setProperty($sName, $mValue)
    {
        $this->node->setProperty($sName, $mValue);
    }

    public function updateProperties($properties)
    {
        return $this->node->updateProperties($properties);
    }

    public function getProperties($properties)
    {
        return $this->node->getProperties($properties);
    }

    public function getResourceData()
    {
        return $this->node->getResourceData();
    }

    public function putResourceData(array $newData)
    {
        $this->node->putResourceData($newData);
    }

    public function deleteResourceData()
    {
        $this->node->deleteResourceData();
    }
}
