<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV;

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
        return (\substr($this->getName(), 0, \strlen(\Afterlogic\DAV\Constants::CALENDAR_DEFAULT_UUID)) === \Afterlogic\DAV\Constants::CALENDAR_DEFAULT_UUID);
    }
}
