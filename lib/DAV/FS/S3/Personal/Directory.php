<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\S3\Personal;

use Sabre\DAV\Exception\NotFound;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Directory extends \Afterlogic\DAV\FS\S3\Directory
{
	use \Afterlogic\DAV\FS\Shared\DirectoryTrait;

	protected $storage = \Aurora\System\Enums\FileStorageType::Personal;

	public function getChild($name)
	{	
		$mResult = false;
		
		try {
			$mResult = parent::getChild($name);
		} catch (\Exception $oEx) {}

		$oSharedChild = $this->getSharedChild($name);
		if ($oSharedChild) {
			$mResult = $oSharedChild;
		}

		if (!$mResult) {
			throw new NotFound();
		}

		return $mResult;
	}

	public function getChildren($sPattern = null)
	{
		return array_merge(
			parent::getChildren($sPattern),
			$this->getSharedChildren()
		);
	}
}
