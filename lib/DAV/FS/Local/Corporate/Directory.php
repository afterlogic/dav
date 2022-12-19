<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Local\Corporate;

use Afterlogic\DAV\FS\HistoryDirectory;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Directory extends \Afterlogic\DAV\FS\Local\Directory
{
    public function __construct($path)
    {
        parent::__construct(\Aurora\System\Enums\FileStorageType::Corporate, $path);
    }

    public function getChild($name)
    {
        $path = $this->checkFileName($name);

        if (is_dir($path)) {
            $ext = strtolower(substr($name, -5));
            if ($ext === '.hist') {
                $result = new HistoryDirectory($this->getStorage(), $path);
            } else {
                $result = new self($path);
            }
        } else {
            $result = new File($path);
        }

        return $result;
    }

    public function getQuotaInfo()
    {
        return [0, 0];
    }
}
