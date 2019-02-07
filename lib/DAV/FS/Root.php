<?php

namespace Afterlogic\DAV\FS;

use Afterlogic\DAV\Constants;

class Root extends \Sabre\DAV\Collection {

	public function getName() 
	{
		return 'files';
	}

	public function getChildren() 
	{
		$aChildren = [];

		$oPersonalFiles = \Aurora\System\Api::GetModule('PersonalFiles'); 
		if ($oPersonalFiles && !$oPersonalFiles->getConfig('Disabled', false)) 
		{
			$aChildren[] = new Personal\Root();
		}

		$oCorpFiles = \Aurora\System\Api::GetModule('CorporateFiles'); 
		if ($oCorpFiles && !$oCorpFiles->getConfig('Disabled', false)) 
		{
			$aChildren[] = new Corporate\Root();
		}

		$oSharedFiles = \Aurora\System\Api::GetModule('SharedFiles'); 
		if ($oSharedFiles && !$oSharedFiles->getConfig('Disabled', false)) 
		{
			$aChildren[] = new Shared\Root();
		}
			
		return $aChildren;
	}
}