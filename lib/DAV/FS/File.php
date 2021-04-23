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
    use HistoryDirectoryTrait;

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

        $this->deleteHistoryDirectory();

		return $result;
    }

    public function getUrl($bWithContentDisposition = false)
    {
        return null;
    }
}
