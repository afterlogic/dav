<?php

namespace Afterlogic\DAV\CalDAV\Schedule;

class Plugin extends \Sabre\CalDAV\Schedule\Plugin {

    /**
     * Returns a list of addresses that are associated with a principal.
     *
     * @param string $principal
     * @return array
     */
    protected function getAddressesForPrincipal($principal) {

        $CUAS = '{' . self::NS_CALDAV . '}calendar-user-address-set';

        $properties = $this->server->getProperties(
            $principal,
            [$CUAS]
        );

        // If we can't find this information, we'll stop processing
        if (!isset($properties[$CUAS])) {
            return;
        }

        $addresses = $properties[$CUAS]->getHrefs();

        $iPos = strpos($principal, 'principals/');
        if ($iPos !== false)
        {
            $addresses[] = 'mailto:' . \trim(substr($principal, $iPos + 11), '/');
        }
        return $addresses;

    }
}
