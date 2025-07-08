<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV\GAB;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Card extends \Sabre\CardDAV\Card
{

    public function put($cardData)
    {
        throw new \Sabre\DAV\Exception\MethodNotAllowed('Put for this card is not allowed.');
    }

    /**
     * Deletes the card.
     */
    public function delete()
    {
        throw new \Sabre\DAV\Exception\MethodNotAllowed('Delete this card is not allowed.');
    }

    public function getACL()
    {
        return [
            [
                'privilege' => '{DAV:}read',
                'principal' => \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . \Afterlogic\DAV\Server::getUser(),
                'protected' => true,
            ],
        ];
    }
}
