<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Backend
{
	public static $aBackends = array();

	public static function __callStatic($sMethod, $aArgs)
	{
		$oResult = null;
		if (!method_exists('Backend', $sMethod)) {

			$oResult = self::getBackend(strtolower($sMethod));
		}
		return $oResult;
	}

	public static function getBackend($sName)
	{
		if (!isset(self::$aBackends[$sName])) {
			$oBackend = null;
			switch ($sName) {
				case 'principal':
					$oBackend = new Principal\Backend\PDO();
					break;
				case 'caldav':
					$oBackend = new CalDAV\Backend\PDO();
					break;
				case 'carddav':
					$oBackend = new CardDAV\Backend\PDO();
					break;
				case 'lock':
					$oBackend = new Locks\Backend\PDO();
					break;
				case 'reminders':
					$oBackend = new Reminders\Backend\PDO();
					break;
				case 'fs':
					$oBackend = new FS\Backend\PDO();
					break;
			}
			if (isset($oBackend)) {

				self::$aBackends[$sName] = $oBackend;
			}
		}
		return self::$aBackends[$sName];
	}

}
