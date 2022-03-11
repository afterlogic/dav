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
class Root extends \Sabre\DAV\Collection {

	use NodeTrait;

	public static $aStoragesCache = null;

	protected $path;

	public function getName()
	{
		return 'files';
	}

	protected function getStorages()
	{
		if (!isset(self::$aStoragesCache)) {
			self::$aStoragesCache = \Aurora\Modules\Files\Module::Decorator()->GetSubModules();
		}

		return self::$aStoragesCache;
	}

	public function getChildrenCount()
	{
		return count($this->getStorages());
	}

	public function getChildren()
	{
		$aChildren = [];
		$aStorages = $this->getStorages();

		foreach ($aStorages as $sStorage) {
			$aClassPath = ['Afterlogic', 'DAV', 'FS'];

			$aStoragePath = \explode('.', $sStorage);
			foreach ($aStoragePath as $sPathItem) {
				$aClassPath[] = \ucfirst($sPathItem);
			}
			$aClassPath[] = 'Root';

			$sClass = \implode(
				'\\',
				$aClassPath
			);

			$aChildren[] = new $sClass();
		}

		return $aChildren;
	}
}
