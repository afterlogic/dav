<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\S3\Personal;

use Aws\S3\S3Client;
use Aws\Credentials\Credentials;
use Afterlogic\DAV\Server;
use Aurora\Modules\S3Filestorage;
use Aurora\System\Api;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Root extends Directory
{
    protected $storage = \Aurora\System\Enums\FileStorageType::Personal;

    protected $client = null;

    public static $childCache;

    public function __construct($sPrefix = null)
    {
        $oModule = S3Filestorage\Module::getInstance();

        $sBucketPrefix = $oModule->getConfig('BucketPrefix');

        $sBucket = \strtolower($sBucketPrefix . \str_replace([' ', '.'], '-', Server::getTenantName()));

        $this->client = $this->getS3Client();

        static $bDoesBucketExist = null;
        $buckets = $this->client->listBuckets();
        foreach ($buckets['Buckets'] as $bucket) {
            if ($bucket['Name'] === $sBucket) {
                $bDoesBucketExist = true;
                break;
            }
        }
        if (!$bDoesBucketExist) {
            $this->createBucket($this->client, $sBucket);
        }

        if (empty($sPrefix)) {
            $sPrefix =  $this->getUser();
        }

        parent::__construct('/' . $sPrefix, $sBucket, $this->client, $this->storage);
    }

    protected function getS3Client()
    {
        static $client = false;
        if (!$client) {
            $oModule = S3Filestorage\Module::getInstance();

            $sRegion = $oModule->getConfig('Region');
            $sAccessKey = $oModule->getConfig('AccessKey');
            $sSecretKey = $oModule->getConfig('SecretKey');

            $credentials = new Credentials($sAccessKey, $sSecretKey);

            $aOptions = [
                'region' => $sRegion,
                'version' => 'latest',
                'credentials' => $credentials,
                'use_path_style_endpoint' => $oModule->getConfig('UsePathStyleEndpoint')
            ];
            $endpoint = $oModule->getConfig('Host');
            if (!empty($endpoint)) {
                $aOptions['endpoint'] = 'https://' . $endpoint;
            }
            $client = new S3Client($aOptions);
        }

        return $client;
    }

    protected function createBucket($client, $sBucket)
    {
        $oModule = S3Filestorage\Module::getInstance();
        $sBucketLocation = $oModule->getConfig('BucketLocation');

        $aOptions = [
            'Bucket' => $sBucket,
        ];

        if (!empty($sBucketLocation)) {
            $aOptions['CreateBucketConfiguration'] = [
                'LocationConstraint' => $sBucketLocation,
            ];
        }

        $res = $client->createBucket($aOptions);
        try {
            $client->putBucketCors([
                'Bucket' => $sBucket,
                'CORSConfiguration' => [
                    'CORSRules' => [
                        [
                            'AllowedHeaders' => [
                                '*',
                            ],
                            'AllowedMethods' => [
                                'GET',
                                'PUT',
                                'POST',
                                'DELETE',
                                'HEAD'
                            ],
                            'AllowedOrigins' => [
                                (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']
                            ],
                            'MaxAgeSeconds' => 0,
                        ],
                    ],
                ],
    //			'ContentMD5' => '',
            ]);
        } catch (\Exception $ex) {
            Api::LogException($ex);
        }
    }

    public function getName()
    {
        return $this->storage;
    }

    public function setName($name)
    {
        throw new \Sabre\DAV\Exception\Forbidden();
    }

    public function delete()
    {
        throw new \Sabre\DAV\Exception\Forbidden();
    }

    protected function getUsedSize($sUserPublicId)
    {
        $iSize = 0;

        if (!empty($sUserPublicId)) {
            $searchResult = $this->client->getPaginator('ListObjectsV2', [
                'Bucket' => $this->bucket,
                'Prefix' => $sUserPublicId . '/'
            ])->search('Contents[?Size.to_number(@) != `0`].Size.to_number(@)');
            $iSize = array_sum(
                iterator_to_array($searchResult)
            );
        }

        return $iSize;
    }

    public function getQuotaInfo()
    {
        $sUserSpaceLimitInMb = -1;

        $oUser = \Aurora\Modules\Core\Module::getInstance()->getUserByPublicId($this->getUser());
        if ($oUser) {
            $sUserSpaceLimitInMb = $oUser->getExtendedProp('Files::UserSpaceLimitMb') * 1024 * 1024;
        }

        return [
            (int) $this->getUsedSize($this->UserPublicId),
            (int) $sUserSpaceLimitInMb
        ];
    }
}
