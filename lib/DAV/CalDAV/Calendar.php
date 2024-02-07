<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV;

use Sabre\DAV\PropPatch;

/**
 * This object represents a CalDAV calendar.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Calendar extends \Sabre\CalDAV\Calendar {

    use CalendarTrait;

    public function getProperties($requestedProperties)
    {
        return $this->_getProperties($this->calendarInfo, $requestedProperties);
    }

    /**
     * Deletes the calendar.
     */
    public function delete()
    {
        if ($this->isMain()) {
            throw new \Sabre\DAV\Exception\Forbidden();
        }

        parent::delete();
    }

    /**
     * Updates properties on this node.
     *
     * This method received a PropPatch object, which contains all the
     * information about the update.
     *
     * To update specific properties, call the 'handle' method on this object.
     * Read the PropPatch documentation for more information.
     */
    public function propPatch(PropPatch $propPatch)
    {
        if ($this->isMain()) {
            throw new \Sabre\DAV\Exception\Forbidden();
        }
        parent::propPatch($propPatch);
    }
}
