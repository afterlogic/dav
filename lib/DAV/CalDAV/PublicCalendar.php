<?php

namespace Afterlogic\DAV\CalDAV;

/**
 * This object represents a CalDAV calendar that is shared by a different user.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class PublicCalendar extends \Sabre\CalDAV\SharedCalendar {

	public function getChildren() {
		$aChildren = parent::getChildren();
		
		return $aChildren;
	}

}
