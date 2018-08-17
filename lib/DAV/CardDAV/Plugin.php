<?php

namespace Afterlogic\DAV\CardDAV;

class Plugin extends Sabre\CardDAV\Plugin {

	/**
     * Returns the addressbook home for a given principal
     *
     * @param string $principal
     * @return string
     */
    protected function getAddressbookHomeForPrincipal($principal) {

        return self::ADDRESSBOOK_ROOT;

    }
}
