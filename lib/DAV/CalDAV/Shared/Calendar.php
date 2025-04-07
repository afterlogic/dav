<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CalDAV\Shared;

use Sabre\DAV\PropPatch;
use Sabre\DAV\Sharing\Plugin as SPlugin;

/**
 * This object represents a CalDAV calendar that is shared by a different user.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Calendar extends \Sabre\CalDAV\SharedCalendar {

    use \Afterlogic\DAV\CalDAV\CalendarTrait;

    public function isOwned()
    {
        return $this->getShareAccess() === \Sabre\DAV\Sharing\Plugin::ACCESS_SHAREDOWNER;
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
        $this->beforePropPatch($propPatch);
        parent::propPatch($propPatch);
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
		$acl = parent::getACL();
		$acl = array_filter($acl, function ($val) {
			return basename($val['principal']) !== 'calendar-proxy-read' && basename($val['principal']) !== 'calendar-proxy-write';
		});
		return $acl;
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
		$acl = parent::getChildACL();
		$acl = array_filter($acl, function ($val) {
			return basename($val['principal']) !== 'calendar-proxy-read' && basename($val['principal']) !== 'calendar-proxy-write';
		});
		return $acl;
    }
}
