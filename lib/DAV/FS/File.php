<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class File extends \Sabre\DAV\FSExt\File implements \Sabre\DAVACL\IACL 
{
    use NodeTrait;
    use PropertyStorageTrait;
    
	public function __construct($storage, $path)
	{
		$this->storage = $storage;
		parent::__construct($path);
    }
    
    public function get($bRedirectToUrl = false)
    {
        return parent::get();
    }
    
    public function getDirectory() 
    {
        list($dir) = \Sabre\Uri\split($this->path);
		return new Directory($dir);
    }
	
    public function delete() 
    {
        $result = parent::delete();

        $this->deleteShares();

		$this->deleteResourceData();
		
		return $result;
    }
	
    /**
     * Renames the node
     *
     * @param string $name The new name
     * @return void
     */
    public function setName($name) 
    {
        list($parentPath, $oldName) = \Sabre\Uri\split($this->path);
        list(, $newName) = \Sabre\Uri\split($name);
        $newPath = $parentPath . '/' . $newName;
        
		$sRelativePath = $this->getRelativePath();

		$oldPathForShare = $sRelativePath . '/' .$oldName;
		$newPathForShare = $sRelativePath . '/' .$newName;

        $oSharedFiles =  \Aurora\System\Api::GetModule('SharedFiles');
        if ($oSharedFiles && !$oSharedFiles->getConfig('Disabled', false))
        {
            $pdo = new Backend\PDO();
            $pdo->updateShare($this->getOwner(), $this->getStorage(), $oldPathForShare, $newPathForShare);
        }

        // We're deleting the existing resourcedata, and recreating it
        // for the new path.
        $resourceData = $this->getResourceData();
        $this->deleteResourceData();

        rename($this->path, $newPath);
        $this->path = $newPath;
        $this->putResourceData($resourceData);
    }

}
