<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\S3\Corporate;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
trait NodeTrait
{
  protected $storage = \Aurora\System\Enums\FileStorageType::Corporate;
  
  /**
   * Renames the node
   *
   * @param string $name The new name
   * @return void
   */
  public function setName($name)
  {
    $path = str_replace($this->storage, '', $this->path);

    list($path, $oldname) = \Sabre\Uri\split($path);

    $this->copyObjectTo($this->storage, $path, $name, true);
  }
}
