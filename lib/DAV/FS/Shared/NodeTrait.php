<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS\Shared;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
trait NodeTrait
{
	public $inRoot;
	protected $node;

    public function getOwner() {

        return $this->principalUri;

    }

    function getETag()
    {
        if (\file_exists($this->path))
        {
            return parent::getETag();
        }
        else
        {
            return '';
        }
    }

    public function getSize()
    {
            return null;
    }

    function getLastModified()
    {
        return null;
    }

    function getQuotaInfo() {}

}
