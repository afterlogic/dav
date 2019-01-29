<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Corporate;

class File extends \Afterlogic\DAV\FS\File{
    
    public function getStorage() {

        return \Aurora\System\Enums\FileStorageType::Corporate;

    }	
}

