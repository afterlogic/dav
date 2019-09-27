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

	public function copyObject($sFromPath, $sToPath, $sOldName, $sNewName, $bIsFolder = false, $bMove = false, $aUpdateMetadata = null)
	{
		$mResult = false;

		$sUserPublicId = $this->getUser();

		$sSuffix = $bIsFolder ? '/' : '';

		$sFullFromPath = $sUserPublicId . \rtrim($sFromPath, '/')  . '/' . $sOldName . $sSuffix;
		$sFullToPath = $sUserPublicId .  \rtrim($sToPath, '/') . '/' . $sNewName. $sSuffix;

		if ($bIsFolder)
		{
			$objects = $this->client->getIterator('ListObjects', array(
				"Bucket" => $this->bucket,
				"Prefix" => $sFullFromPath //must have the trailing forward slash "/"
			));	

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
					'CopySource' => $this->bucket . "/" . $sKey,
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
				'CopySource' => $this->bucket . '/' . $sFullFromPath,
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

		$this->copyObject($path, $path, $oldname, $name, $this->isDirectory(), true);
	}	
	
	public function isDirectory()
	{
		return ($this instanceof Directory);
	}

}
