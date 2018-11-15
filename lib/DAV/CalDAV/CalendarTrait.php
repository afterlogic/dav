<?php

namespace Afterlogic\DAV\CalDAV;

trait CalendarTrait {

    protected function _getProperties($calendarInfo, $requestedProperties = []) {

        $response = [];

        foreach ($calendarInfo as $propName => $propValue) {

            if (!is_null($propValue) && (in_array($propName, $requestedProperties) || count($requestedProperties) === 0))
                $response[$propName] = $calendarInfo[$propName];

        }
        return $response;

    }    
}