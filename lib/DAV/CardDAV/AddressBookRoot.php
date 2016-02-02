<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CardDAV;

class AddressBookRoot extends \Sabre\CardDAV\AddressBookRoot
{
	protected $oAccount = null;
	
	protected function getAccount($principalUri)
	{
		if (null === $this->oAccount)
		{
			$this->oAccount = \Afterlogic\DAV\Utils::GetAccountByLogin(
					basename($principalUri)
			);
		}
		return $this->oAccount;
	}

	public function getChildForPrincipal(array $aPrincipal)
	{
		/* @var \CApiCapabilityManager */
		$oApiCapabilityManager = \CApi::GetCoreManager('capability');
		
		$oAccount = $this->getAccount($aPrincipal['uri']);
		$bEmpty = !($oAccount instanceof \CAccount &&
			$oApiCapabilityManager->isPersonalContactsSupported($oAccount));
		
		$oAddressBookHome = new AddressBookHome(
				$this->carddavBackend, 
				$aPrincipal['uri']
		);
		$oAddressBookHome->setEmpty($bEmpty);
		
		return $oAddressBookHome;
    }

}
