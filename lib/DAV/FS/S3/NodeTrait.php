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
trait NodeTrait 
{
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
		if ($oModuleManager->IsAllowedModule('PersonalFiles')) 
		{
			\Aurora\Modules\PersonalFiles\Module::Decorator()->UpdateUsedSpace();
		}
	}	

	public function getPathForS3($sPath)
	{
		$sStorage = substr($sPath , 0,  8);
		if ($sStorage === 'personal')
		{
			$sPath = substr_replace($sPath, $this->getUser(), 0, 8);
		}

		return $sPath;
	}

	public function copyObjectTo($sToStorage, $sToPath, $sNewName, $bMove = false, $aUpdateMetadata = null)
	{
		$mResult = false;

		if ($sToStorage === 'shared') return false; // TODO:

		$sUserPublicId = $this->getUser();
		\Afterlogic\DAV\Server::getInstance()->setUser($sUserPublicId);

		$sFullFromPath = $this->getPathForS3($this->getPath());		
		$sFullToPath = $this->getPathForS3($sToStorage . \rtrim($sToPath, '/') . '/' . $sNewName . ($this->isDirectoryObject() ? '/' : ''));		

		if ($this->isDirectoryObject())
		{
			$objects = $this->client->getIterator('ListObjectsV2', [
				"Bucket" => $this->bucket,
				"Prefix" => $sFullFromPath //must have the trailing forward slash "/"
			]);	

			$aKeys = [];
			$batchHeadObject = [];
			foreach ($objects as $object)
			{
				$aKeys[$object['ETag']] = $object['Key'];
				$batchHeadObject[] = $this->client->getCommand('HeadObject', [
					'Bucket'     => $this->bucket,
					'Key' => $object['Key']
				]);
			}
			
			$aSubMetadata = [];
			$HeadObjectResults = \Aws\CommandPool::batch($this->client, $batchHeadObject);
			foreach($HeadObjectResults as $result) 
			{
				if ($result instanceof \Aws\ResultInterface) 
				{
					$aSubMetadata[$result['ETag']] = $result->get('Metadata');
					if (!$bMove)
					{
						$aSubMetadata[$result['ETag']]['GUID'] = \Sabre\DAV\UUIDUtil::getUUID();
					}
				}
			}

			$batchCopyObject = [];
			foreach ($aKeys as $sETag => $sKey) 
			{
				$sNewKey = \str_replace($sFullFromPath, $sFullToPath, $sKey);
				$batchCopyObject[] = $this->client->getCommand('CopyObject', [
					'Bucket'     => $this->bucket,
					'Key'        => $sNewKey,
					'CopySource' => $this->bucket . "/" . \Aws\S3\S3Client::encodeKey($sKey),
					'Metadata' => $aSubMetadata[$sETag],
					'MetadataDirective' => 'REPLACE'
				]);				
			}
			try 
			{
				\Aws\CommandPool::batch($this->client, $batchCopyObject);
				$mResult = true;
			} 
			catch (\Exception $e) 
			{
				$mResult = false;
			}	
			
			if ($bMove)
			{
				// 3. Delete the objects.
				$this->client->deleteObjects([
					'Bucket'  => $this->bucket,
					'Delete' => [
						'Objects' => array_map(function($sKey) {return ['Key' => $sKey];}, $aKeys)
					],
				]);
			}
		}
		else
		{
			$oObject = $this->client->HeadObject([
				'Bucket' => $this->bucket,
				'Key' => $sFullFromPath
			]);
			
			$aMetadata = [];
			$sMetadataDirective = 'COPY';
			if ($oObject)
			{
				$aMetadata = $oObject->get('Metadata');
				$sMetadataDirective = 'REPLACE';
			}
	
			if (is_array($aUpdateMetadata))
			{
				$aMetadata = array_merge($aMetadata, $aUpdateMetadata);
			}
			if (!$bMove)
			{
				$aMetadata['GUID'] = \Sabre\DAV\UUIDUtil::getUUID();
			}

			$res = $this->client->copyObject([
				'Bucket' => $this->bucket,
				'Key' => $sFullToPath,
				'CopySource' => $this->bucket . '/' . \Aws\S3\S3Client::encodeKey($sFullFromPath),
				'Metadata' => $aMetadata,
				'MetadataDirective' => $sMetadataDirective
			]);
			if ($res && $bMove)	
			{
				$this->client->deleteObject([
					'Bucket' => $this->bucket,
					'Key' => $sFullFromPath
				]);					

				$mResult = true;
			}
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
		$sUserPublicId = $this->getUser();

		$path = str_replace($sUserPublicId, '', $this->path);

		list($path, $oldname) = \Sabre\Uri\split($path);

		$this->copyObjectTo($this->getStorage(), $path, $name, true);
	}	
	
	public function isDirectoryObject()
	{
		return ($this instanceof Directory);
	}

}
