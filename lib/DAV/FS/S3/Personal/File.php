<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\S3\Personal;

use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class File extends \Afterlogic\DAV\FS\S3\File
{
    protected $storage = \Aurora\System\Enums\FileStorageType::Personal;
}
