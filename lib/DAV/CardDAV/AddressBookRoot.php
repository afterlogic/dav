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
			$oCoreModuleDecorador = \CApi::GetModuleDecorator('Core');
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
		/* @var \CApiCapabilityManager */
		$oApiCapabilityManager = \CApi::GetCoreManager('capability');
		
//		$oAccount = $this->getAccount($aPrincipal['uri']);
		$bEmpty = false;/*!($oAccount instanceof \CAccount &&
			$oApiCapabilityManager->isPersonalContactsSupported($oAccount));*/
		
		$oAddressBookHome = new AddressBookHome(
				$this->carddavBackend, 
				$aPrincipal['uri']
		);
		$oAddressBookHome->setEmpty($bEmpty);
		
		return $oAddressBookHome;
    }

}
