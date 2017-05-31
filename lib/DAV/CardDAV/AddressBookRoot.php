<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CardDAV;

class AddressBookRoot extends \Sabre\CardDAV\AddressBookRoot
{
	protected $iUserId = null;
	
	protected function getUser($principalUri)
	{
		if (null === $this->iUserId )
		{
			$oCoreModuleDecorador = \Aurora\Modules\Core\Module::Decorator();
			if ($oCoreModuleDecorador)
			{
				$oUser = $oCoreModuleDecorador->GetUser(basename($principalUri));
				if ($oUser)
				{
					$this->iUserId = basename($principalUri);
				}
			}
		}
		return $this->iUserId;
	}

	public function getChildForPrincipal(array $aPrincipal)
	{
		$bEmpty = false;
		
		$oAddressBookHome = new AddressBookHome(
				$this->carddavBackend, 
				$aPrincipal['uri']
		);
		$oAddressBookHome->setEmpty($bEmpty);
		
		return $oAddressBookHome;
    }

}
