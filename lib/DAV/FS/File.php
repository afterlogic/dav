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
		return new Directory($this->storage, $dir);
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

    function patch($data, $rangeType, $offset = null) {

        switch ($rangeType) {
            case 0 :
                $f = fopen($this->path, 'w');
                break;
            case 1 :
                $f = fopen($this->path, 'a');
                break;
            case 2 :
                $f = fopen($this->path, 'c');
                fseek($f, $offset);
                break;
            case 3 :
                $f = fopen($this->path, 'c');
                fseek($f, $offset, SEEK_END);
                break;
        }
        if (is_string($data)) {
            fwrite($f, $data);
        } else {
            stream_copy_to_stream($data, $f);
        }
        fclose($f);
        clearstatcache(true, $this->path);
        return $this->getETag();

    }
}
