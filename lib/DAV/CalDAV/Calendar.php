<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CalDAV;

class Calendar extends \Sabre\CalDAV\Calendar{
	
	public function getId()
	{
		return $this->calendarInfo['id'];
	}
	
}