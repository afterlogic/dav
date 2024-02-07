<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV;

use Sabre\CalDAV;
use Sabre\DAV;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\MkCol;
use Sabre\DAVACL;
use Sabre\Uri;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
trait CalendarTrait
{
    public function _getProperties($calendarInfo, $requestedProperties = [])
    {
        $response = [];

        foreach ($calendarInfo as $propName => $propValue)
        {
            if (!is_null($propValue) && (in_array($propName, $requestedProperties) || count($requestedProperties) === 0))
            {
                $response[$propName] = $calendarInfo[$propName];
            }
        }
        return $response;
    }

    public function isDefault()
    {
        $bIsDefault = false;
        $oUser = \Aurora\Api::getAuthenticatedUser();
        if ($oUser && $oUser->{'Calendar::DefaultCalendar'} && $this->getName() === $oUser->{'Calendar::DefaultCalendar'}) {
            $bIsDefault = true;
        }
        
        return $bIsDefault;
    }

    public function isMain()
    {
        return strpos($this->getName(), 'MyCalendar') === 0;
    }
}
