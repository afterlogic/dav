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
trait StorageTrait
{

	public function getSyncToken()
	{
		$pdo = new Backend\PDO();
		return $pdo->getSyncToken($this->getOwner(), $this->getName());
	}

	public function getChanges($syncToken, $syncLevel, $limit = null)
	{
		$pdo = new Backend\PDO();
		return $pdo->getChanges($this->getOwner(), $this->getName(), $syncToken, $syncLevel, $limit);
	}

	public function addChange($objectUri, $operation, $newname = '')
	{
		$pdo = new Backend\PDO();
		return $pdo->addChange($this->getOwner(), $this->getName(), $objectUri, $operation, $newname);
	}
}
