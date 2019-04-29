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
		return 'personal';
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

		$sFullFromPath = $this->bucket . '/' . $sUserPublicId . $sFromPath . '/' . $sOldName . $sSuffix;
		$sFullToPath = $sUserPublicId . $sToPath.'/'.$sNewName. $sSuffix;

		$oObject = $this->client->HeadObject([
			'Bucket' => $this->bucket,
			'Key' => $sUserPublicId . $sFromPath . '/' . $sOldName . $sSuffix
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

		$res = $this->client->copyObject([
			'Bucket' => $this->bucket,
			'Key' => $sFullToPath,
			'CopySource' => $sFullFromPath,
			'Metadata' => $aMetadata,
			'MetadataDirective' => $sMetadataDirective
		]);

		if ($res)	
		{
			if ($bMove)
			{
				$res = $this->client->deleteObject([
					'Bucket' => $this->bucket,
					'Key' => $sUserPublicId . $sFromPath.'/'.$sOldName . $sSuffix
				]);					
			}
			$mResult = true;
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
