<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Local\Personal;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Root extends Directory
{
    protected $storage = \Aurora\System\Enums\FileStorageType::Personal;

    public function __construct($sUserPublicId = null)
    {
        $path = \Aurora\System\Api::DataPath() . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_ROOT;

        if (!\is_dir($path)) {
            @\mkdir($path);
        }

        $path = $path . \Afterlogic\DAV\Constants::FILESTORAGE_PATH_PERSONAL;

        if (!\is_dir($path)) {
            @\mkdir($path);
        }

        if (empty($sUserPublicId)) {
            $sUserPublicId = $this->getUser();
        }
        $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($sUserPublicId);

        if ($oUser) {
            $path = $path . '/' . $oUser->UUID;
            if (!\is_dir($path)) {
                @\mkdir($path, 0777, true);
            }
        }
        parent::__construct($path);
    }

    public function getName()
    {
        return $this->storage;
    }

    public function setName($name)
    {
        throw new \Sabre\DAV\Exception\Forbidden();
    }

    public function delete()
    {
        throw new \Sabre\DAV\Exception\Forbidden();
    }

    protected function getUsedSize()
    {
        $sRootPath = $this->getRootPath();
        $aSize = \Aurora\System\Utils::GetDirectorySize($sRootPath);
        return (int) $aSize['size'];
    }

    public function getQuotaInfo()
    {
        $sUserSpaceLimitInMb = -1;

        $oUser = \Aurora\Modules\Core\Module::Decorator()->GetUserByPublicId($this->UserPublicId);
        if ($oUser) {
            $sUserSpaceLimitInMb = $oUser->getExtendedProp('Files::UserSpaceLimitMb') * 1024 * 1024;
        }

        return [
            (int) $this->getUsedSize(),
            (int) $sUserSpaceLimitInMb
        ];
    }

    public function getRelativePath()
    {
        return "";
    }
}
