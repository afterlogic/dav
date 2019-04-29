<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\Logs;

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
    private $server;

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
    }

    public function initialize(\Sabre\DAV\Server $server)
    {
        $this->server = $server;
        $this->server->on('beforeMethod', array($this, 'beforeMethod'), 30);
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
        return 'logs';
    }

    /**
     * This method is called before any HTTP method, but after authentication.
     *
     * @param string $sMethod
     * @param string $path
     * @throws \Sabre\DAV\Exception\NotAuthenticated
     * @return bool
     */
    public function beforeMethod($sMethod, $path)
    {
		$aHeaders = $this->server->httpRequest->getHeaders();

    	\Aurora\System\Api::Log($sMethod . ' ' . $path, \Aurora\System\Enums\LogLevel::Full, 'sabredav-');
    	\Aurora\System\Api::LogObject($aHeaders, \Aurora\System\Enums\LogLevel::Full, 'sabredav-');

		$bLogBody = (bool) \Aurora\Modules\Dav\Module::getInstance()->getConfig('LogBody', false);
		if ($bLogBody)
		{
			$body = $this->server->httpRequest->getBodyAsString(); 		
			$this->server->httpRequest->setBody($body);
			\Aurora\System\Api::LogObject($body, \Aurora\System\Enums\LogLevel::Full, 'sabredav-');
		}
		\Aurora\System\Api::Log('', \Aurora\System\Enums\LogLevel::Full, 'sabredav-');

    	return;
    }

}
