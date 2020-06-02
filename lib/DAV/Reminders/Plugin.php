<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\Reminders;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Plugin extends \Sabre\DAV\ServerPlugin {

    /**
     * Reference to Server class
     *
     * @var \Sabre\DAV\Server
     */
    private $server;

    /**
     * cacheBackend
     *
     * @var Backend\PDO
     */
    private $backend;

    /**
     * Returns a plugin name.
     *
     * Using this name other plugins will be able to access other plugins
     * using \Sabre\DAV\Server::getPlugin
     *
     * @return string
     */
    public function getPluginName() {

        return 'reminders';

    }

	/**
     * __construct
     *
     * @param Backend\PDO $backend
     * @return void
     */
    public function __construct(Backend\PDO $backend = null) {

        $this->backend = $backend;
    }

	/**
     * Initializes the plugin and registers event handlers
     *
     * @param \Sabre\DAV\Server $server
     * @return void
     */
    public function initialize(\Sabre\DAV\Server $server)
	{

        $this->server = $server;

		$this->server->on('beforeMethod', array($this, 'beforeMethod'), 90);
		$this->server->on('afterCreateFile', array($this, 'afterCreateFile'), 90);
		$this->server->on('afterWriteContent', array($this, 'afterWriteContent'), 90);
    }

    /**
     * @param string $method
     * @param string $uri
     * @return void
     */
    public function beforeMethod($method, $uri)
	{
		if (Backend\PDO::isCalendar($uri)) {

			if (strtoupper($method) == 'DELETE') {

				if (Backend\PDO::isEvent($uri)) {

					$this->deleteReminder(Backend\PDO::getEventId($uri));
				} else {

					$this->deleteReminderByCalendar($uri);
				}
			}
		}
    }

	public function afterCreateFile($uri, \Sabre\DAV\ICollection $parent)
	{
		if (Backend\PDO::isEvent($uri))
		{
			$node = $parent->getChild(Backend\PDO::getEventUri($uri));
			if ($node)
			{
				$this->updateReminder($uri, $node->get(), \Afterlogic\DAV\Server::getUser());
			}
		}
	}

	public function afterWriteContent($uri, \Sabre\DAV\IFile $node)
	{
		if (Backend\PDO::isEvent($uri) && $node)
		{
			$this->updateReminder($uri, $node->get(), \Afterlogic\DAV\Server::getUser());
		}
	}

	public function getReminder($eventId, $user = null)
	{
		return $this->backend->getReminder($eventId, $user);
	}

	public function getReminders($start, $end)
	{
		return $this->backend->getReminders($start, $end);
	}

	public function addReminder($user, $calendarUri, $eventId, $time = null, $starttime = null, $allday = false)
	{
		return $this->backend->addReminder($user, $calendarUri, $eventId, $time, $starttime, $allday);
	}

	public function deleteReminder($eventId, $user = null)
	{
		$this->backend->deleteReminder($eventId, $user);
	}

	public function deleteReminderByCalendar($calendarUri)
	{
		$this->backend->deleteReminderByCalendar($calendarUri);
	}

	public function updateReminder($uri, $data, $user)
	{
		$this->backend->updateReminder($uri, $data, $user);
	}
}
