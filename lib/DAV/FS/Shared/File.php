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
        if ($this->node) {

            return $this->node->getLastModified();
        }

        return 0;
    }

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getSize()
    {
        if ($this->node) {

            return $this->node->getSize();
        }

        return 0;
    }

    function get($bRedirectToUrl = true)
    {
        if ($this->node) {

            return $this->node->get($bRedirectToUrl);
        }

        return '';
    }

    public function put($data)
    {
        $aExtendedProps = $this->node->getProperty('ExtendedProps');
        if ($this->node && !(is_array($aExtendedProps) && isset($aExtendedProps['InitializationVector']))) {

            return $this->node->put($data);
        } else {

            return false;
        }
    }

    public function getHistoryDirectory()
    {
        $oNode = null;
        if ($this->node) {

            list(, $owner) = \Sabre\Uri\split($this->getOwner());
            Server::getInstance()->setUser($owner);
            try
            {
                $oNode = Server::getNodeForPath('files/'. $this->node->getStorage() . $this->node->getRelativePath() . '/' . $this->node->getName() . '.hist');
            }
            catch (\Exception $oEx) {}
        }

		return $oNode;
    }

    function patch($data, $rangeType, $offset = null) 
    {
        $aExtendedProps = $this->node->getProperty('ExtendedProps');
        if ($this->node && !(is_array($aExtendedProps) && isset($aExtendedProps['InitializationVector']))) {

            return $this->node->patch($data, $rangeType, $offset);
        } else {

            return false;
        }
    }
}
