<?php


namespace Afterlogic\DAV\FS;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class HistoryDirectory extends Directory
{
    public function getFileVersion()
    {
        $ver = 0;
        
        foreach ($this->getChildren() as $oChild) {
            if ($oChild instanceof Directory) {
                $ver++;
            }
        }

        return $ver;
    }

	public function getVersionDir($version, $createdIsNotExists = false)
	{
        if ($this->childExists($version)) {
            return $this->getChild($version);
        } else if ($createdIsNotExists) {
            $this->createDirectory($version);
            return $this->getChild($version);
        } else {
            return false;
        }
	}
}