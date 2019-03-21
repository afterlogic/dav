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
	
	public function get() 
	{
        $request = $this->client->createPresignedRequest(
            $this->client->getCommand(
                'GetObject', 
                [
                    'Bucket' => $this->bucket,
                    'Key' => $this->path,
                ]
            ), 
            '+5 minutes'
        );	
        
        $bNeedToOpen = false;
        $aPathInfo = pathinfo($this->path);

        if ((isset($aPathInfo['extension']) && strtolower($aPathInfo['extension']) === 'url') || 
            strtoupper(\MailSo\Base\Http::SingletonInstance()->GetMethod()) === 'COPY' || 
            (string) \Aurora\System\Application::GetPathItemByIndex(2, '') === 'thumb')
        {
            $bNeedToOpen = true;            
        }

        if ($bNeedToOpen)
        {
            return fopen((string) $request->getUri(), 'rb');		
        }
        else
        {
            header('Location: ' . (string) $request->getUri());
            exit;        
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

