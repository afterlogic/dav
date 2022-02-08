<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\S3;

use Afterlogic\DAV\Constants;
use Afterlogic\DAV\FS\S3\Personal\Root;
use Afterlogic\DAV\Server;
use Aws\Common\Exception\MultipartUploadException;
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

	public function Search($sPattern, $sPath = null)
	{
		return $this->getChildren($sPattern) ;
	}

    protected function isCorporate($sPath)
    {
        return \substr($sPath, 0, 9) === \Aurora\System\Enums\FileStorageType::Corporate;
    }

    protected function isDirectory($sPath)
    {
        return \substr($sPath, -1) === '/';
    }

	protected function getItem($object)
	{
		$result = null;
		
		if ($this->isDirectory($object['Key'])) {

			if ($this->isCorporate($object['Key'])) {

				$result = new Corporate\Directory($object, $this->bucket, $this->client, $this->storage);
			} else {

				$result = new Personal\Directory($object, $this->bucket, $this->client, $this->storage);
			}
		} else {
			if ($this->isCorporate($object['Key'])) {

				$result = new Corporate\File($object, $this->bucket, $this->client, $this->storage);
			}
			else {

				$result = new Personal\File($object, $this->bucket, $this->client, $this->storage);
			}
		}

		return $result;
	}

	public function getChildren($sPattern = null)
	{
		$children = [];

		$Path =  rtrim(ltrim($this->path, '/'), '/') . '/';

		if (!isset(Root::$childrenCache[$Path]) || isset($sPattern)) {

			$iSlashesCount = substr_count($Path, '/');

			$results = $this->client->getPaginator('ListObjectsV2', [
				'Bucket' => $this->bucket,
				'Prefix' => $Path
			]);

			foreach ($results->search('Contents[?starts_with(Key, `' . $Path . '`)]') as $item)
			{
				$sItemNameLowercase = \mb_strtolower(\urldecode(\basename($item['Key'])));
				if (!empty($sPattern) && \mb_strpos($sItemNameLowercase, \mb_strtolower($sPattern)) !== false || empty($sPattern))
				{
					$iItemSlashesCount = substr_count($item['Key'], '/');
					if ($iItemSlashesCount === $iSlashesCount && substr($item['Key'], -1) !== '/' ||
						$iItemSlashesCount === $iSlashesCount + 1 && substr($item['Key'], -1) === '/' || !empty($sPattern))
					{
						$children[] = $this->getItem($item);
					}
				}
			}

			foreach ($children as $iKey => $oChild) {
				$ext = strtolower(substr($oChild->getName(), -5));
				if ($oChild->getName() === '.sabredav' || ($oChild instanceof Directory && $ext === '.hist')) {
					unset($children[$iKey]);
				}
			}

			if ($sPattern) {
				return $children;
			} else {
				Root::$childrenCache[$Path] = $children;
			}
		}

        return Root::$childrenCache[$Path];
	}

    /**
     * Returns a specific child node, referenced by its name
     *
     * This method throw DAV\Exception\NotFound if the node does not exist.
     *
     * @param string $name
     * @throws DAV\Exception\NotFound
     * @return DAV\INode
     */
	function getChild($name)
	{
		$Path = rtrim($this->path, '/').'/'.$name;
		if (isset(Root::$childCache[$Path])) {
			return Root::$childCache[$Path];
		} else {
			foreach ($this->getChildren() as $oChild) {
				if ($oChild->getName() === $name) {
					Root::$childCache[\rtrim($Path)] = $oChild;
					return $oChild;
				}
			}
		}

		// if not a file nor a directory, throw an exception
        throw new \Sabre\DAV\Exception\NotFound('The file with name: ' . $name . ' could not be found');
	}


	public function createDirectory($name)
	{
		$Path = rtrim($this->path, '/').'/'. $name . '/';
		$mResult = $this->client->putObject([
			'Bucket' => $this->bucket,
			'Key' => $Path
		]);
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
				$rData = fopen('php://memory','r+');
				fwrite($rData, $data);
				rewind($rData);
			}

			// Prepare the upload parameters.
			$uploader = new MultipartUploader($this->client, $rData, [
				'Bucket' => $this->bucket,
				'Key'    => $Path,
			]);

			// Perform the upload.
			try
			{
				$uploader->upload();

				$oFile = $this->getChild($name);
				if ($oFile instanceof \Afterlogic\DAV\FS\File)
				{

				// 	if ($rangeType !== 0)
				// 	{
				// 		$oFile->patch($data, $rangeType, $offset);
				// 	}

					$aProps = $oFile->getProperties(['Owner', 'ExtendedProps']);

					if (!isset($aProps['Owner']))
					{
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
			}
			catch (ExceptionMultipartUploadException $e)
			{
				return false;
			}
		}
	}

	function getQuotaInfo() {
		$oRoot = Server::getNodeForPath('files/personal');
		if ($oRoot) {
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
    function getLastModified() {

        if (isset($this->object)) {
            return $this->object['LastModified']->getTimestamp();
        }

	}

    public function delete()
    {
        $res = $this->client->deleteMatchingObjects(
            $this->bucket,
            rtrim($this->path, '/') . '/'
		);

		$this->deleteShares();
	}

	function moveInto($targetName, $sourcePath, \Sabre\DAV\INode $sourceNode)
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
		}
		catch (\Exception $oEx) {}
		return ($oFile instanceof \Afterlogic\DAV\FS\File);
	}
}
