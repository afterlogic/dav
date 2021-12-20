<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Shared;

use Afterlogic\DAV\Constants;
use Afterlogic\DAV\Server;
use Aurora\System\Enums\FileStorageType;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class File extends \Afterlogic\DAV\FS\File implements \Sabre\DAVACL\IACL
{
    use PropertyStorageTrait;
    use NodeTrait;

    public function __construct($name, $node)
    {
        $this->name = $name;
        $this->node = $node;
    }

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getLastModified()
    {
        return $this->node->getLastModified();
    }

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getSize()
    {
        return $this->node->getSize();
    }

    function get($bRedirectToUrl = true)
    {
        return $this->node->get($bRedirectToUrl);
    }

    public function put($data)
    {
        return $this->node->put($data);
    }
}
