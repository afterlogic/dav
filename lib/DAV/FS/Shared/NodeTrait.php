<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

trait NodeTrait 
{
	public $inRoot;
	protected $node;

    public function getOwner() {

        return $this->principalUri;

    }

    function getETag() 
    {
        if (\file_exists($this->path))
        {
            return parent::getETag();
        }
        else
        {
            return '';
        }
    }

    public function getSize()
    {
            return null;
    }
    
    function getLastModified() 
    {
        return null;
    }    

    function getQuotaInfo() {}
   
}
