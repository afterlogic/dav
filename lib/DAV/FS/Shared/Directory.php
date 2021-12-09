<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Shared;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Directory extends \Afterlogic\DAV\FS\Directory
{
    use PropertyStorageTrait;
    use NodeTrait;

    public function __construct($name, $node)
    {
        $this->name = $name;
        $this->node = $node;
        $this->setAccess($node->getAccess());
    }

    public function getDisplayName()
	{
        return $this->getName();
	}

    public function getChild($path)
    {
        $oChild = $this->node->getChild($path);
        if ($oChild)
        {
            $oChild->setAccess($this->getAccess());
            if ($oChild instanceof \Afterlogic\DAV\FS\File)
			{
				$oChild = new File($oChild->getName(), $oChild);
			}
			else if ($oChild instanceof \Afterlogic\DAV\FS\Directory)
			{
				$oChild = new Directory($oChild->getName(), $oChild);
			}
            $oChild->setInherited(true);
        }

        return $oChild;
    }

    public function getChildren()
    {
        $aResult = [];
        $aChildren = $this->node->getChildren();
        foreach ($aChildren as $oChild) {
            $oResult = false;
            $oChild->setAccess($this->getAccess());
            if ($oChild instanceof \Afterlogic\DAV\FS\File) {
				$oResult = new File($oChild->getName(), $oChild);
			} else if ($oChild instanceof \Afterlogic\DAV\FS\Directory) {
				$oResult = new Directory($oChild->getName(), $oChild);
			}
            if ($oResult) {
                $oResult->setInherited(true);
                $aResult[] = $oResult;
            }
        }
        return $aResult;
    }

    public function childExists($name)
    {
        return $this->node->childExists($name);
    }

	public function createDirectory($name)
	{
        $this->node->createDirectory($name);
    }

	public function createFile($name, $data = null, $rangeType = 0, $offset = 0, $extendedProps = [])
	{
        return $this->node->createFile($name, $data, $rangeType, $offset, $extendedProps);
    }
}
