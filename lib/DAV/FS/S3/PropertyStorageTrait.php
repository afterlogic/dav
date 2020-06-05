<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\S3;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
trait PropertyStorageTrait
{

    protected $aJsonProperties = [
        'extendedprops'
    ];

    public function getProperty($sName)
    {
        $prop = null;

        try
        {
            $object = $this->client->HeadObject([
                'Bucket' => $this->bucket,
                'Key' => $this->path
            ]);

            $aMetadata = $object->get('Metadata');

            if (isset($aMetadata[\strtolower($sName)]))
            {
                if (in_array(\strtolower($sName), $this->aJsonProperties))
                {
                    $prop = \json_decode($aMetadata[\strtolower($sName)], true);
                }
                else
                {
                    $prop = $aMetadata[\strtolower($sName)];
                }
            }
        }
        catch(\Exception $oEx){}

        return $prop;
    }

    public function setProperty($sName, $mValue)
    {
		$sUserPublicId = $this->getUser();
		$path = str_replace($sUserPublicId, '', $this->path);
        list($path, $name) = \Sabre\Uri\split($path);
        $path = \rtrim($path, '/') . '/';
        if (in_array(\strtolower($sName), $this->aJsonProperties))
        {
            $mValue = \json_encode($mValue);
        }
        $aUpdateMetadata[\strtolower($sName)] = $mValue;

        $this->copyObjectTo($this->getStorage(), $path, $name, false, $aUpdateMetadata);
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

        list($path, $name) = \Sabre\Uri\split($this->path);
        $path = \rtrim($path, '/') . '/';

        $aUpdateMetadata = [];
        foreach ($properties as $sName => $mValue)
        {
            if (in_array(\strtolower($sName), $this->aJsonProperties))
            {
                $mValue = \json_encode($mValue);
            }
            $aUpdateMetadata[\strtolower($sName)] = $mValue;
        }

        $this->copyObjectTo($this->getStorage(), $path, $name, false, $aUpdateMetadata);
    }

    /**
     * Returns a list of properties for this nodes.;
     *
     * @param array $properties
     * @return array
     */
    function getProperties($properties)
    {
        $props = [];

        try
        {
            $object = $this->client->HeadObject([
                'Bucket' => $this->bucket,
                'Key' => $this->path
            ]);

            $aMetadata = $object->get('Metadata');

            foreach ($properties as $value)
            {
                if (isset($aMetadata[\strtolower($value)]))
                {
                    if (in_array(\strtolower($value), $this->aJsonProperties))
                    {
                        $props[$value] = \json_decode($aMetadata[\strtolower($value)], true);
                    }
                    else
                    {
                        $props[$value] = $aMetadata[\strtolower($value)];
                    }
                }
            }
        }
        catch(\Exception $oEx){}

        return $props;
    }
}
