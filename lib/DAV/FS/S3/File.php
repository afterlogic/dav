<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\S3;

use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class File extends \Afterlogic\DAV\FS\File
{
    use NodeTrait;
    use PropertyStorageTrait;

	protected $client;
	protected $bucket;
	protected $object;
    protected $storage;

	public function __construct($object, $bucket, $client, $storage = null)
	{
		$this->path = ltrim($object['Key'], '/');

		$this->bucket = $bucket;
		$this->client = $client;
		$this->object = $object;
		$this->storage = $storage;
	}

	public function delete()
	{
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $this->path
        ]);
		$this->deleteResourceData();
        $this->deleteShares();
        $this->deleteHistoryDirectory();
	}

    public function put($data)
	{
        $rData = $data;
        if (!is_resource($data))
        {
            $rData = fopen('php://memory','r+');
            fwrite($rData, $data);
            rewind($rData);
        }

        // Prepare the upload parameters.
        $uploader = new MultipartUploader($this->client, $rData, [
            'Bucket' => $this->bucket,
            'Key'    => $this->path
        ]);

        // Perform the upload.
        try
        {
            $uploader->upload();

            return true;
        }
        catch (MultipartUploadException $e)
        {
            return false;
        }
    }

    protected function getObject($bWithContentDisposition = false)
    {
        $fileName = \basename($this->path);

        $aArgs = [
            'Bucket' => $this->bucket,
            'Key' => $this->path,
            'ResponseContentType' => \Aurora\System\Utils::MimeContentType($fileName),
        ];

        if ($bWithContentDisposition) {
            $aArgs['ResponseContentDisposition'] = "attachment; filename=\"". $fileName . "\"";
        }

        $oS3Filestorage = \Aurora\Modules\S3Filestorage\Module::getInstance();
        $iPresignedLinkLifetime = 60;
        if ($oS3Filestorage) {
            $iPresignedLinkLifetime = $oS3Filestorage->getConfig('PresignedLinkLifeTimeMinutes', $iPresignedLinkLifetime);
        }

        return $this->client->createPresignedRequest(
            $this->client->getCommand(
                'GetObject',
                $aArgs
            ),
            '+' . $iPresignedLinkLifetime . ' minutes'
        );
    }

    public function getUrl($bWithContentDisposition = false)
    {
        $sUrl = null;
        $oObject = $this->getObject($bWithContentDisposition);
        if ($oObject) {
            $sUrl = (string) $oObject->getUri();
        }

        return $sUrl;
    }

    public function get($bRedirectToUrl = null)
    {
        if ($bRedirectToUrl === null) {
            $oS3FilestorageModule = \Aurora\System\Api::GetModule('S3Filestorage');
            $bRedirectToUrl = $oS3FilestorageModule ? $oS3FilestorageModule->getConfig('RedirectToOriginalFileURLs', true) : true;
        }

        $sUrl = $this->getUrl();
        if (!empty($sUrl)) {
            $aPathInfo = pathinfo($this->path);

            if ((isset($aPathInfo['extension']) && strtolower($aPathInfo['extension']) === 'url') ||
                strtoupper(\MailSo\Base\Http::SingletonInstance()->GetMethod()) === 'COPY' || !$bRedirectToUrl) {
                $context = stream_context_create(array(
                    "ssl"=>array(
                        "verify_peer"=>false,
                        "verify_peer_name"=>false,
                    )
                ));
                return fopen($sUrl, 'rb', false, $context);
            } else {
                \Aurora\System\Api::Location($sUrl);
                exit;
            }
        }
    }

	public function getWithContentDisposition()
	{
        $sUrl = $this->getUrl(true);
        if (!empty($sUrl)) {
            \Aurora\System\Api::Location($sUrl);
            exit;
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

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getSize() 
    {
        if (isset($this->object)) {
            return $this->object['Size'];
        }

    }

    public function getETag()
    {
        if (isset($this->object)) {
            return $this->object['ETag'];
        }
    }
}
