<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Logs;

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

    	\Aurora\System\Api::Log($sMethod . ' ' . $path, \ELogLevel::Full, 'sabredav-');
    	\Aurora\System\Api::LogObject($aHeaders, \ELogLevel::Full, 'sabredav-');

		$bLogBody = (bool) \Aurora\System\Api::GetConf('labs.dav.log-body', false);
		if ($bLogBody) {
			
			$body = $this->server->httpRequest->getBodyAsString(); 		
			$this->server->httpRequest->setBody($body);
			\Aurora\System\Api::LogObject($body, \ELogLevel::Full, 'sabredav-');
		}
		\Aurora\System\Api::Log('', \ELogLevel::Full, 'sabredav-');

    	return;
    }

}

