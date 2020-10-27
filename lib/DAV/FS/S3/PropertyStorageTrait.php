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
    protected $cache = [];

    public function getResourceRawData($path)
    {
        if (!isset($this->cache[$path]))
        {
            $data = [];
            $oObject = false;
            try
            {
                $oObject = $this->client->getObject([
                        'Bucket' => $this->bucket,
                        'Key' => $path
                    ]
                );
            }
            catch (\Exception $oEx){}
            if ($oObject)
            {
                $mFileData = (string) $oObject['Body'];
                $data = unserialize($mFileData);
            }
            $this->cache[$path] = $data;
        }
        return $this->cache[$path];
    }

    /**
     * Returns all the stored resource information
     *
     * @return array
     */
    public function getResourceData()
    {
        $data = $this->getResourceRawData($this->getResourceInfoPath());
        if (!isset($data[$this->getName()]))
        {
            $data[$this->getName()] = ['properties' => []];
        }

        $data = $data[$this->getName()];
        if (!isset($data['properties'])) $data['properties'] = [];

        if (!isset($data['properties']['ExtendedProps']))
        {
            $aMetadata = $this->getMetadata();
            if (isset($aMetadata[\strtolower('ExtendedProps')]))
            {
                $data['properties']['ExtendedProps'] = \json_decode($aMetadata[\strtolower('ExtendedProps')], true);
            }
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
        $data = $this->getResourceRawData($path);
        $data[$this->getName()] = $newData;

        $rData = fopen('php://memory','r+');
        fwrite($rData, \serialize($data));
        rewind($rData);

        // Prepare the upload parameters.
        $uploader = new \Aws\S3\MultipartUploader($this->client, $rData, [
            'Bucket' => $this->bucket,
            'Key'    => $path
        ]);

        // Perform the upload.
        try
        {
            $uploader->upload();
            $this->cache[$path] = $data;
            return true;
        }
        catch (\Aws\Exception\MultipartUploadException $e)
        {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function deleteResourceData()
    {
        $path = $this->getResourceInfoPath();
        $data = $this->getResourceRawData($path);

        // Unserializing and checking if the resource file contains data for this file
        if (isset($data[$this->getName()]))
        {
            unset($data[$this->getName()]);
        }

        $rData = fopen('php://memory','r+');
        fwrite($rData, \serialize($data));
        rewind($rData);

        // Prepare the upload parameters.
        $uploader = new \Aws\S3\MultipartUploader($this->client, $rData, [
            'Bucket' => $this->bucket,
            'Key'    => $path
        ]);

        // Perform the upload.
        try
        {
            $uploader->upload();
            $this->cache[$path] = $data;
            return true;
        }
        catch (\Aws\Exception\MultipartUploadException $e)
        {
            return false;
        }

        return true;
    }

    public function getMetadata()
    {
        $oObject = $this->client->HeadObject([
            'Bucket' => $this->bucket,
            'Key' => $this->getPathForS3($this->getPath())
        ]);

        $aMetadata = [];
        if ($oObject)
        {
            $aMetadata = $oObject->get('Metadata');
        }

        return $aMetadata;
    }

    public function putResourceRawData($path, array $aData)
    {
        $data = $this->getResourceRawData($path);
        foreach ($aData as $name => $newData)
        {
            $data[$name] = $newData;
        }

        $rData = fopen('php://memory','r+');
        fwrite($rData, \serialize($data));
        rewind($rData);

        // Prepare the upload parameters.
        $uploader = new \Aws\S3\MultipartUploader($this->client, $rData, [
            'Bucket' => $this->bucket,
            'Key'    => $path
        ]);

        // Perform the upload.
        try
        {
            $uploader->upload();
            $this->cache[$path] = $data;
            return true;
        }
        catch (\Aws\Exception\MultipartUploadException $e)
        {
            return false;
        }
    }
}
