<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV;

class Subscription extends \Sabre\CalDAV\Subscriptions\Subscription {

    public function getBaseCalendarId() {
        return $this->subscriptionInfo['id'];
    }

}