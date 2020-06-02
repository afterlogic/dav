<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV;

use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Plugin extends \Sabre\CardDAV\Plugin {

	/**
     * Returns the addressbook home for a given principal
     *
     * @param string $principal
     * @return string
     */
    protected function getAddressbookHomeForPrincipal($principal) {

        return self::ADDRESSBOOK_ROOT;

    }

    /**
     * This event is triggered after GET requests.
     *
     * This is used to transform data into jCal, if this was requested.
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return void
     */
    function httpAfterGet(RequestInterface $request, ResponseInterface $response) {
        try
        {
            parent::httpAfterGet($request, $response);
        }
        catch (\Exception $oEx)
        {
            $mBody = $response->getBody();
            if (is_resource($mBody))
            {
                \rewind($mBody);
            }
        }
    }
}
