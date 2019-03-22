<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\S3;

use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;

class File extends \Afterlogic\DAV\FS\File
{
    use NodeTrait;
    use PropertyStorageTrait;
    
	protected $client;
	protected $bucket;
	protected $object;

	public function __construct($object, $bucket, $client) 
	{
		$this->path = ltrim($object['Key'], '/');

		$this->bucket = $bucket;
		$this->client = $client;
		$this->object = $object;
	}
	
	public function delete() 
	{
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $this->path
        ]);	
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

    protected function getObject()
    {
        return $this->client->createPresignedRequest(
            $this->client->getCommand(
                'GetObject', 
                [
                    'Bucket' => $this->bucket,
                    'Key' => $this->path,
                ]
            ), 
            '+5 minutes'
        );	
    }
    
    public function getUrl()
    {
        $sUrl = null;
        $oObject = $this->getObject();
        if ($oObject)
        {
            $sUrl = (string) $oObject->getUri();
        }

        return $sUrl;
    }

    public function getBody()
    {
        $mResult = null;
        $sUrl = $this->getUrl();
        if (!empty($sUrl))
        {
            $mResult = fopen($sUrl, 'rb');		
        }

        return $mResult;
    }

	public function get() 
	{
        $sUrl = $this->getUrl();
        if (!empty($sUrl))
        {
            $aPathInfo = pathinfo($this->path);
                        
            if ((isset($aPathInfo['extension']) && strtolower($aPathInfo['extension']) === 'url') || 
                strtoupper(\MailSo\Base\Http::SingletonInstance()->GetMethod()) === 'COPY')
            {
                return fopen($sUrl, 'rb');		
            }
            else
            {
                \Aurora\System\Api::Location($sUrl);
            }
        }
	}    

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getLastModified() {
        
        if (isset($this->object))
        {
            return $this->object['LastModified']->getTimestamp();
        }

	}		
	
    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getSize() {

        if (isset($this->object))
        {
            return $this->object['Size'];
        }

    }		

    public function getETag()
    {
        if (isset($this->object))
        {
            return $this->object['ETag'];
        }
    }
}

