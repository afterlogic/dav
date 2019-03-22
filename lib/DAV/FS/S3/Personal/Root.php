<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\S3\Personal;

use Aws\S3\S3Client;
use Afterlogic\DAV\Server;
use Aurora\Modules\S3Filestorage;

class Root extends Directory 
{
	protected $client = null;

	public function __construct($sUserPublicId = null) 
	{
		$oModule = S3Filestorage\Module::getInstance();

		$sBucketPrefix = $oModule->getConfig('BucketPrefix');

		$sBucket = \strtolower($sBucketPrefix . Server::getTenantName());

		$sHost = $oModule->getConfig('Host');
		$sRegion = $oModule->getConfig('Region');
		$endpoint = "https://".$sRegion.".".$sHost;

		$client = $this->getS3Client($endpoint);

		if(!$client->doesBucketExist($sBucket)) 
		{
			$client->createBucket([
				'Bucket' => $sBucket
			]);
		}

		$endpoint = "https://".$sBucket.".".$sRegion.".".$sHost;
		$this->client = $this->getS3Client($endpoint, true);

		if (empty($sUserPublicId))
		{
			$sUserPublicId =  $this->getUser();
		}

		$path = '/' . $sUserPublicId;

		parent::__construct($path, $sBucket, $this->client);
	}

	protected function getS3Client($endpoint, $bucket_endpoint = false)
	{
		$oModule = S3Filestorage\Module::getInstance();

		$signature_version = 'v4';
		if (!$bucket_endpoint)
		{
			$signature_version = 'v4-unsigned-body';
		}

		$sRegion = $oModule->getConfig('Region');
		$sAccessKey = $oModule->getConfig('AccessKey');
		$sSecretKey = $oModule->getConfig('SecretKey');


		return S3Client::factory([
			'region' => $sRegion,
			'version' => 'latest',
			'endpoint' => $endpoint,
			'credentials' => [
				'key'    => $sAccessKey,
				'secret' => $sSecretKey,
			],
			'bucket_endpoint' => $bucket_endpoint,
			'signature_version' => $signature_version
		]);					
	}	

	public function getName() 
	{
        return 'personal';
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

		if (!empty($sUserPublicId))
		{
			$oSearchResult = $this->client->getPaginator('ListObjectsV2', [
				'Bucket' => $this->bucket,
				'Prefix' => $sUserPublicId . '/'
			])
			->search('Contents[?Size.to_number(@) != `0`].Size.to_number(@)');
			
			foreach ($oSearchResult as $size)
			{
				$iSize += $size;
			}
		}
		
		return $iSize;
	}

	public function getQuotaInfo()
	{
 		return [
			$this->getUsedSize($this->UserPublicId),
			S3Filestorage\Module::getInstance()->getConfig('UserSpaceLimitMb', 0) * 1024 * 1024
		];
    }	
}
