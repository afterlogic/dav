<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV\SharedWithAll;

/**
 * This object represents a CalDAV calendar that is shared by a different user.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Calendar extends \Afterlogic\DAV\CalDAV\Shared\Calendar
{
    use \Afterlogic\DAV\CalDAV\CalendarTrait;

    public function getProperties($requestedProperties)
    {
        return $this->_getProperties($this->calendarInfo, $requestedProperties);
    }

 /**
     * Returns a list of ACE's for this node.
     *
     * Each ACE has the following properties:
     *   * 'privilege', a string such as {DAV:}read or {DAV:}write. These are
     *     currently the only supported privileges
     *   * 'principal', a url to the principal who owns the node
     *   * 'protected' (optional), indicating that this ACE is not allowed to
     *      be updated.
     *
     * @return array
     */
    public function getACL()
    {
        $sUser = \Afterlogic\DAV\Server::getUser();
        if ($sUser) {
            $this->calendarInfo['principaluri'] = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $sUser;
        }

        return parent::getACL();
    }


    /**
     * This method returns the ACL's for calendar objects in this calendar.
     * The result of this method automatically gets passed to the
     * calendar-object nodes in the calendar.
     *
     * @return array
     */
    public function getChildACL()
    {
        $sUser = \Afterlogic\DAV\Server::getUser();
        if ($sUser) {
            $this->calendarInfo['principaluri'] = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . $sUser;
        }

        return parent::getChildACL();
    }

    public function delete()
    {
        throw new \Sabre\DAV\Exception\Forbidden();
    }
}
