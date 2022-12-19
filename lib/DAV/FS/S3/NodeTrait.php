<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\S3;

use Afterlogic\DAV\FS\NodeTrait as FSNodeTrait;
use Afterlogic\DAV\Server;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
trait NodeTrait
{
    use \Afterlogic\DAV\FS\HistoryDirectoryTrait;
    use FSNodeTrait;
    use PropertyStorageTrait;

    public function getPath()
    {
        return $this->path;
    }

    public function getStorage()
    {
        return $this->storage;
    }

    protected function updateUsedSpace()
    {
        $oModuleManager = \Aurora\System\Api::GetModuleManager();
        if ($oModuleManager->IsAllowedModule('PersonalFiles')) {
            \Aurora\Modules\PersonalFiles\Module::Decorator()->UpdateUsedSpace();
        }
    }

    public function getPathForS3($sPath)
    {
        $sStorage = substr($sPath, 0, 8);
        if ($sStorage === 'personal') {
            $sPath = substr_replace($sPath, $this->getUser(), 0, 8);
        }

        return $sPath;
    }

    public function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    public function getCopySource($sKey)
    {
        return $this->bucket . "/" . \Aws\S3\S3Client::encodeKey($sKey);
    }

    public function updateMetadata($aUpdateMetadata)
    {
        $sFullFromPath = $this->getPathForS3($this->getPath());
        $sFullToPath = $sFullFromPath;
        $oObject = $this->client->HeadObject([
            'Bucket' => $this->bucket,
            'Key' => $sFullFromPath
        ]);

        $aMetadata = [];
        $sMetadataDirective = 'COPY';
        if ($oObject) {
            $aMetadata = $oObject->get('Metadata');
            $sMetadataDirective = 'REPLACE';
        }

        if (is_array($aUpdateMetadata)) {
            $aMetadata = array_merge($aMetadata, $aUpdateMetadata);
        }

        return $this->client->copyObject([
            'Bucket' => $this->bucket,
            'Key' => $sFullToPath,
            'CopySource' => $this->getCopySource($sFullFromPath),
            'Metadata' => $aMetadata,
            'MetadataDirective' => $sMetadataDirective
        ]);
    }

    public function copyObjectTo($sToStorage, $sToPath, $sNewName, $bMove = false, $aUpdateMetadata = null)
    {
        $mResult = false;

        if ($sToStorage === 'shared') {
            return false;
        } // TODO:

        $sUserPublicId = $this->getUser();
        Server::getInstance()->setUser($sUserPublicId);

        $sFullFromPath = $this->getPathForS3($this->getPath());
        $sFullToPath = $this->getPathForS3($sToStorage . \rtrim($sToPath, '/') . '/' . $sNewName . ($this->isDirectoryObject() ? '/' : ''));

        $aProps = $this->getProperties([]);
        if (!$bMove) {
            if (!isset($aProps['ExtendedProps'])) {
                $aProps['ExtendedProps'] = [];
            }
            $aProps['ExtendedProps']['GUID'] =  \Sabre\DAV\UUIDUtil::getUUID();
        }

        if ($this->isDirectoryObject()) {
            $objects = $this->client->getIterator('ListObjectsV2', [
                "Bucket" => $this->bucket,
                "Prefix" => $sFullFromPath //must have the trailing forward slash "/"
            ]);

            $aKeys = [];
            foreach ($objects as $object) {
                $aKeys[] = $object['Key'];
            }

            $batchCopyObject = [];
            foreach ($aKeys as $sKey) {
                $sNewKey = \str_replace($sFullFromPath, $sFullToPath, $sKey);
                $batchCopyObject[] = $this->client->getCommand('CopyObject', [
                    'Bucket'     => $this->bucket,
                    'Key'        => $sNewKey,
                    'CopySource' => $this->getCopySource($sKey)
                ]);
            }

            $oResults = \Aws\CommandPool::batch($this->client, $batchCopyObject);
            foreach ($oResults as $oResult) {
                if ($oResult instanceof \Aws\S3\Exception\S3Exception) {
                    \Aurora\Api::LogException($oResult, \Aurora\System\Enums\LogLevel::Full);
                }
            }
            $mResult = true;
        } else {
            $res = $this->client->copyObject([
                'Bucket' => $this->bucket,
                'Key' => $sFullToPath,
                'CopySource' => $this->getCopySource($sFullFromPath)
            ]);

            if ($res) {
                $sToPathInfo = $this->getPathForS3($sToStorage . \rtrim($sToPath, '/') . '/.sabredav');
                $aToProps = $this->getResourceRawData($sToPathInfo);
                $aToProps[$sNewName]['properties'] = $aProps;
                $this->putResourceRawData($sToPathInfo, $aToProps);

                $mResult = true;
            }
        }

        if ($bMove) {
            $this->delete();
        }

        return $mResult;
    }

    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name)
    {
        list($parentPath, $oldName) = \Sabre\Uri\split($this->path);
        list(, $newName) = \Sabre\Uri\split($name);
        $newPath = $parentPath . '/' . $newName;

        $this->setNameShared($name);

        // We're deleting the existing resourcedata, and recreating it
        // for the new path.
        $resourceData = $this->getResourceData();
        $this->deleteResourceData();

        $sUserPublicId = $this->getUser();
        $path = str_replace($sUserPublicId, '', $this->path);
        list($path, $oldname) = \Sabre\Uri\split($path);
        $this->copyObjectTo($this->getStorage(), $path, $name, true);


        $this->path = $newPath;
        $this->putResourceData($resourceData);

        $this->setNameHistory($name);
    }

    public function isDirectoryObject()
    {
        return ($this instanceof Directory);
    }

    public function getRelativePath()
    {
        list($sPath) = \Sabre\Uri\split($this->getPath());

        $aPathItems =  explode('/', $sPath, 2);

        return isset($aPathItems[1]) ? '/' . $aPathItems[1] : '';
    }
}
