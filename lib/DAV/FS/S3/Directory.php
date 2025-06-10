<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\S3;

use Afterlogic\DAV\FS\Root;
use Afterlogic\DAV\Server;
use Aws\Exception\MultipartUploadException as ExceptionMultipartUploadException;
use Aws\S3\MultipartUploader;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Directory extends \Afterlogic\DAV\FS\Directory
{
    use NodeTrait;

    /**
     * @var \Aws\S3\S3ClientInterface
     */
    protected $client;

    /**
     * @var [string]
     */
    protected $bucket;

    protected $object;

    protected $objects;

    protected $storage;

    /**
     * Undocumented function
     *
     * @param [type] $object
     * @param [string] $bucket
     * @param [S3Client] $client
     */
    public function __construct($object, $bucket, $client, $storage = null)
    {
        if (is_string($object)) {
            $path = $object;
        } else {
            $path = $object['Key'];
            $this->object = $object;
        }
        // remove first '/' char if any provided
        $this->path = ltrim($path, '/');

        $this->bucket = $bucket;
        $this->client = $client;
        $this->storage = $storage;
    }

    protected function isCorporate($sPath)
    {
        return \substr($sPath, 0, 9) === \Aurora\System\Enums\FileStorageType::Corporate;
    }

    protected function isDirectory($sPath)
    {
        return \substr($sPath, -1) === '/';
    }

    protected function getItem($object, $isDir = false)
    {
        $result = null;

        if ($isDir) {
            if ($this->isCorporate($object['Key'])) {
                $result = new Corporate\Directory($object, $this->bucket, $this->client, $this->storage);
            } else {
                $result = new Personal\Directory($object, $this->bucket, $this->client, $this->storage);
            }
        } else {
            if ($this->isCorporate($object['Key'])) {
                $result = new Corporate\File($object, $this->bucket, $this->client, $this->storage);
            } else {
                $result = new Personal\File($object, $this->bucket, $this->client, $this->storage);
            }
        }

        return $result;
    }

    public function getChildren()
    {
        $children = [];

        if (isset(Root::$childrenCache[$this->getStorage()][$this->getPath()])) {
            $children = Root::$childrenCache[$this->getStorage()][$this->getPath()];
        } else {
            $result = JmesQuery::getInstance($this->client, $this->bucket)
                ->query($this->getPath(), ['limit' => 0]);

            $children = array_map(function ($child) {
                return $this->getItem($child, $child['IsDir']);
            }, $result);
            Root::$childrenCache[$this->getStorage()][$this->getPath()] = $children;
        }

        return $children;
    }

    /**
     * Returns a specific child node, referenced by its name
     *
     * This method throw DAV\Exception\NotFound if the node does not exist.
     *
     * @param string $name
     * @throws \Sabre\DAV\Exception\NotFound
     * @return \Sabre\DAV\INode
     */
    public function getChild($name)
    {
        $fullPath = $this->path === '' ? $name : \rtrim($this->path, '/') . '/' . $name;
        
        // Trying to check for the presence of "folder" (prefix)
        $result = $this->client->listObjectsV2([
            'Bucket' => $this->bucket,
            'Prefix' => $fullPath . '/',
            'MaxKeys' => 1,
        ]);
    
        if (!empty($result['Contents'])) {
            // There is content, so the folder exists.
            return $this->getItem($result['Contents'][0], true);
        } else {
            // Trying to get a file
            try {
                $result = $this->client->headObject([
                    'Bucket' => $this->bucket,
                    'Key' => $fullPath,
                ]);
                $item = [
                    'Key' => $fullPath,
                    'LastModified' => $result['LastModified'],
                    'Size' => $result['ContentLength']
                ];
                // If the call is successful, we return the file object
                return $this->getItem($item, false);
            } catch (\Aws\Exception\AwsException $e) {
                // if not a file nor a directory, throw an exception
                throw new \Sabre\DAV\Exception\NotFound('The object with name: ' . $name . ' could not be found');
            }
        }
    }

    public function createDirectory($name)
    {
        $Path = rtrim($this->path, '/').'/'. $name . '/';
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $Path
        ]);

        if ($this->client->getEndpoint()->getHost() === 'storage.googleapis.com') { // workaround for GCS
            $oDirectory = $this->getChild($name);
            if ($oDirectory instanceof \Afterlogic\DAV\FS\Directory) {    
                $oDirectory->setProperty('lastModified', time());
            }
        }
    }

    public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = [])
    {
        $Path = rtrim($this->path, '/').'/'.$name;

        if ($this->childExists($name)) {
            $oChild = $this->getChild($name);
            return $oChild->put($data);
        } else {
            $rData = $data;
            if (!is_resource($data)) {
                $rData = fopen('php://memory', 'r+');
                fwrite($rData, $data);
                rewind($rData);
            }

            // Prepare the upload parameters.
            $uploader = new MultipartUploader($this->client, $rData, [
                'Bucket' => $this->bucket,
                'Key'    => $Path,
            ]);

            // Perform the upload.
            try {
                $uploader->upload();

                $oFile = $this->getChild($name);
                if ($oFile instanceof \Afterlogic\DAV\FS\File) {
                    // 	if ($rangeType !== 0)
                    // 	{
                    // 		$oFile->patch($data, $rangeType, $offset);
                    // 	}

                    $aProps = $oFile->getProperties(['Owner', 'ExtendedProps']);

                    if (!isset($aProps['Owner'])) {
                        $aProps['Owner'] = $this->getUser();
                    }

                    $extendedProps['GUID'] = \Sabre\DAV\UUIDUtil::getUUID();
                    $aCurrentExtendedProps = $extendedProps;
                    if (!isset($aProps['ExtendedProps'])) {
                        foreach ($extendedProps as $sPropName => $propValue) {
                            if ($propValue === null) {
                                unset($aCurrentExtendedProps[$sPropName]);
                            } else {
                                $aCurrentExtendedProps[$sPropName] = $propValue;
                            }
                        }
                    }
                    $aProps['ExtendedProps'] = $aCurrentExtendedProps;

                    $oFile->updateProperties($aProps);
                }
                return true;
            } catch (ExceptionMultipartUploadException $e) {
                return false;
            }
        }
    }

    public function getQuotaInfo()
    {
        $oRoot = Server::getNodeForPath('files/personal');
        if ($oRoot instanceof Personal\Root) {
            return $oRoot->getQuotaInfo();
        } else {
            return [0, 0];
        }
    }

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    public function getLastModified()
    {   
        $lastModified = isset($this->object) && isset($this->object['LastModified']) && $this->object['LastModified'] instanceof \DateTime ? $this->object['LastModified']->getTimestamp() : null;
        if (!$lastModified) { // workaround for GCS
            $lastModified = $this->getProperty('lastModified');
        }

        return $lastModified;
    }

    public function delete()
    {
        if ($this->client->getEndpoint()->getHost() === 'storage.googleapis.com') { // workaround for GCS
            // gets all objects with prefix
            $objects = $this->client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => rtrim($this->path, '/') . '/'
            ]);

            // delete objects
            $batchDeleteObject = [];
            foreach ($objects['Contents'] as $object) {
                $batchDeleteObject[] = $this->client->getCommand('DeleteObject', [
                    'Bucket' => $this->bucket,
                    'Key'    => $object['Key']
                ]);
            }
            $oResults = \Aws\CommandPool::batch($this->client, $batchDeleteObject);
            foreach ($oResults as $oResult) {
                if ($oResult instanceof \Aws\S3\Exception\S3Exception) {
                    \Aurora\Api::LogException($oResult, \Aurora\System\Enums\LogLevel::Full);
                }
            }
        } else {
            $this->client->deleteMatchingObjects(
                $this->bucket,
                rtrim($this->path, '/') . '/'
            );
        }

        $this->deleteShares();
        $this->deleteFavorites();
    }

    public function moveInto($targetName, $sourcePath, \Sabre\DAV\INode $sourceNode)
    {
        // We only support Directory or File objects, so
        // anything else we want to quickly reject.
        if (!$sourceNode instanceof self && !$sourceNode instanceof File) {
            return false;
        }

        $sUserPublicId = $this->getUser();
        $toPath = rtrim(str_replace($sUserPublicId, '', $this->getPath()), '/');


        $this->copyObjectTo($this->getStorage(), $toPath, $targetName, true);

        return true;
    }

    public function childExists($name)
    {
        $oFile = null;
        try {
            $oFile = $this->getChild($name);
        } catch (\Exception $oEx) {
        }
        return ($oFile instanceof \Sabre\DAV\FS\Node);
    }
}
