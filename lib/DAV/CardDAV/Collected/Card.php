<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV\Collected;

use Sabre\DAV\Exception\Forbidden;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Card extends \Sabre\CardDAV\Card
{
    /**
     * Deletes the card
     *
     * @return void
     */
    public function delete()
    {
//        throw new Forbidden();
    }
}
