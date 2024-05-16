<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
trait PropertyStorageTrait
{
    public function getProperty($sName)
    {
        $aData = $this->getResourceData();
        return isset($aData['properties'][$sName]) ? $aData['properties'][$sName] : null;
    }

    public function setProperty($sName, $mValue)
    {
        $aData = $this->getResourceData();
        $aData['properties'][$sName] = $mValue;
        $this->putResourceData($aData);
    }

    /**
     * Updates properties on this node,
     *
     * @param array $properties
     * @see Sabre\DAV\IProperties::updateProperties
     * @return bool|array
     */
    public function updateProperties($properties)
    {
        $resourceData = $this->getResourceData();

        foreach ($properties as $propertyName=>$propertyValue) {
            // If it was null, we need to delete the property
            if (is_null($propertyValue)) {
                if (isset($resourceData['properties'][$propertyName])) {
                    unset($resourceData['properties'][$propertyName]);
                }
            } else {
                $resourceData['properties'][$propertyName] = $propertyValue;
            }
        }

        $this->putResourceData($resourceData);
        return true;
    }

    /**
     * Returns a list of properties for this nodes.;
     *
     * The properties list is a list of propertynames the client requested, encoded as xmlnamespace#tagName, for example: http://www.example.org/namespace#author
     * If the array is empty, all properties should be returned
     *
     * @param array $properties
     * @return array
     */
    public function getProperties($properties)
    {
        $resourceData = $this->getResourceData();

        // if the array was empty, we need to return everything
        if (!$properties) {
            return $resourceData['properties'];
        }

        $props = [];
        foreach ($properties as $property) {
            if (isset($resourceData['properties'][$property])) {
                $props[$property] = $resourceData['properties'][$property];
            }
        }

        return $props;
    }

    /**
     * Returns the path to the resource file
     *
     * @return string
     */
    public function getResourceInfoPath()
    {
        list($parentDir) = \Sabre\Uri\split($this->path);
        return $parentDir . '/.sabredav';
    }

    public function getData($string) 
    {
        $result = json_decode($string, true);
        $json = json_last_error() === JSON_ERROR_NONE ? $result : false;

        if ($json !== false) {
            $data = $json;
        } else {
            // Unserializing and checking if the resource file contains data for this file
            $data = unserialize($string);
        }

        return $data;
     }

     public function readResourceData($path)
     {
        $data = '';

        // opening up the file, and creating a shared lock
        $handle = fopen($path, 'a+');;

        // Reading data until the eof
        while (!feof($handle)) {
            $data .= fread($handle, 8192);
        }

        $data = $this->getData($data);

        // We're all good
        fclose($handle);

        return $data;
     }

    /**
     * Returns all the stored resource information
     *
     * @return array
     */
    public function getResourceData()
    {
        $path = $this->getResourceInfoPath();
        if (!file_exists($path)) {
            return ['properties' => []];
        }

        $data = $this->readResourceData($path);

        if (!isset($data[$this->getName()])) {
            return ['properties' => []];
        }

        $data = $data[$this->getName()];
        if (!isset($data['properties'])) {
            $data['properties'] = [];
        }
        return $data;
    }

    /**
     * Updates the resource information
     *
     * @param array $newData
     * @return void
     */
    public function putResourceData(array $newData)
    {
        $path = $this->getResourceInfoPath();

        $data = $this->readResourceData($path);

        $handle2 = fopen($path, 'w');
        $data[$this->getName()] = $newData;

        rewind($handle2);
        fwrite($handle2, json_encode($data));
        fclose($handle2);
    }

    /**
     * @return bool
     */
    public function deleteResourceData()
    {
        // When we're deleting this node, we also need to delete any resource information
        $path = $this->getResourceInfoPath();
        if (!file_exists($path)) {
            return true;
        }

        // opening up the file, and creating a shared lock
        $handle = fopen($path, 'a+');
        flock($handle, LOCK_EX);
        $data = '';

        rewind($handle);

        // Reading data until the eof
        while (!feof($handle)) {
            $data .= fread($handle, 8192);
        }

        $data = $this->getData($data);
        if (isset($data[$this->getName()])) {
            unset($data[$this->getName()]);
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data));
        fclose($handle);

        return true;
    }
}
