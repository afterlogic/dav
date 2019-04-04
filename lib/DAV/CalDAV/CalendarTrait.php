<?php

namespace Afterlogic\DAV\CalDAV;

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
        return ($this->getName() === \Afterlogic\DAV\Constants::CALENDAR_DEFAULT_UUID);
    }
}