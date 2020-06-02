<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\PropertyStorage\Backend;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class PDO extends \Sabre\DAV\PropertyStorage\Backend\PDO
{
	/**
	 * Creates the backend
	 */
	public function __construct()
	{
		parent::__construct(\Aurora\System\Api::GetPDO());

		$this->dBPrefix = \Aurora\System\Api::GetSettings()->DBPrefix;
		$this->tableName = $this->dBPrefix.'adav_propertystorage';
	}
}
