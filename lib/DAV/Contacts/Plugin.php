<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\Contacts;


/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Plugin extends \Sabre\DAV\ServerPlugin
{
    /**
     * Reference to main server object
     *
     * @var \Sabre\DAV\Server
     */
    private $oServer;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function initialize(\Sabre\DAV\Server $oServer)
    {
        $this->oServer = $oServer;
        $this->oServer->on('beforeUnbind', array($this, 'beforeUnbind'), 30);
        $this->oServer->on('beforeCreateFile', array($this, 'beforeCreateFile'), 30);
    }

    /**
     * Returns a plugin name.
     *
     * Using this name other plugins will be able to access other plugins
     * using \Sabre\DAV\Server::getPlugin
     *
     * @return string
     */
    public function getPluginName()
    {
        return 'contacts';
    }

    public static function isContact($uri)
    {
        $sUriExt = \pathinfo($uri, PATHINFO_EXTENSION);
        return ($sUriExt != null && strtoupper($sUriExt) == 'VCF');
    }

    /**
     * @param string $sPath
     * @throws \Sabre\DAV\Exception\NotAuthenticated
     * @return bool
     */
    public function beforeUnbind($sPath)
    {
        return true;
    }

    public function beforeCreateFile($path, &$data, \Sabre\DAV\ICollection $parent, &$modified)
    {
        if (self::isContact($path)) {
            if ($parent->childExists(\basename($path))) {
                throw new \Sabre\DAV\Exception\Conflict();
            }
        }
    }
}
