<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CalDAV;

class ShareableCalendar extends \Sabre\CalDAV\ShareableCalendar{
	
	public function getId()
	{
		return $this->calendarInfo['id'];
	}
	
}