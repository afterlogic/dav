<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV\GAB;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class AddressBook extends \Sabre\DAV\Collection implements \Sabre\CardDAV\IDirectory, \Sabre\DAV\IProperties, \Sabre\DAVACL\IACL {

    /**
	 * @var string
     */
    private $name;

    /**
	 * @var array
     */
	private $addressBookInfo;

    /**
	 * @var int
     */
	private $iUserId;
	
	/**
     * Constructor
     */
    public function __construct($name, $displayname = '')
	{
		$this->name = $name;
		$this->iUserId = null;

		$this->addressBookInfo['{DAV:}displayname'] = (empty($displayname)) ? $name : $displayname;
    }


	public function getUser()
	{
		if ($this->iUserId == null) {
			
			$this->iUserId = \Afterlogic\DAV\Server::getUser();
		}
		return $this->iUserId;
	}

	/**
     * @return string
     */
    public function getName()
	{
        return $this->name;
    }
	
	public function getChild($name)
	{
		$aPathInfo = pathinfo($name);
		
		$oContact = \Aurora\System\Managers\Eav::getInstance()->getEntity($aPathInfo['filename'], 'Aurora\Modules\Contacts\Classes\Contact');
		if ($oContact)
		{
			$aName = [$oContact->LastName, $oContact->FirstName];
			$vCard = new \Sabre\VObject\Component\VCard(
				[
					'VERSION' => '3.0',
					'UID' => $oContact->UUID,
					'FN' => $oContact->FullName,
					'N' => (empty($oContact->LastName) && empty($oContact->FirstName)) ? explode(' ', $oContact->FullName) : $aName
				]
			);

			$vCard->add(
				'EMAIL',
				$oContact->ViewEmail,
				[
					'type' => ['work'],
					'pref' => 1,
				]
			);				

			return new Card(
				[
					'uri' => $oContact->UUID . '.vcf',
					'carddata' => $vCard->serialize(),
					'lastmodified' => strtotime($oContact->DateModified)
				]
			);
		}
		else
		{
	        throw new \Sabre\DAV\Exception\NotFound();
		}
		
	}

    /**
     * @return array
     */
    public function getChildren()
	{
        $aCards = [];

		$aContacts = \Aurora\System\Managers\Eav::getInstance()->getEntities(
			\Aurora\Modules\Contacts\Classes\Contact::class,
			[
				'LastName', 'FirstName', 'FullName', 'ViewEmail', 'DateModified'
			], 
			0, 
			0, 
			['Storage' => 'team']
		);
		
		if (is_array($aContacts) && count($aContacts) > 0)
		{
			foreach($aContacts as $oContact) {

				$aName = [$oContact->LastName, $oContact->FirstName];
				$vCard = new \Sabre\VObject\Component\VCard(
					[
						'VERSION' => '3.0',
						'UID' => $oContact->UUID,
						'FN' => $oContact->FullName,
						'N' => (empty($oContact->LastName) && empty($oContact->FirstName)) ? explode(' ', $oContact->FullName) : $aName
					]
				);

				$vCard->add(
					'EMAIL',
					$oContact->ViewEmail,
					[
						'type' => ['work'],
						'pref' => 1,
					]
				);				

				$aCards[] = new Card(
					[
						'uri' => $oContact->UUID . '.vcf',
						'carddata' => $vCard->serialize(),
						'lastmodified' => strtotime($oContact->DateModified)
					]
				);
			}
		}
        return $aCards;
    }

    public function getCTag() {

		$iResult = \Aurora\System\Managers\Eav::getInstance()->getEntitiesCount(
			\Aurora\Modules\Contacts\Classes\Contact::class,
			['Storage' => 'team']
		);
		
		$aContacts = \Aurora\System\Managers\Eav::getInstance()->getEntities(
			\Aurora\Modules\Contacts\Classes\Contact::class,
			[
				'DateModified'
			], 
			0, 
			0, 
			['Storage' => 'team'],
			['DateModified'], 
			\Aurora\System\Enums\SortOrder::DESC				
		);
		if (is_array($aContacts) && isset($aContacts[0]))
		{
			$iResult .= strtotime($aContacts[0]->DateModified);
		}
		
		return (int) $iResult;
	}
	
    public function getProperties($properties) {

		$this->addressBookInfo['{http://calendarserver.org/ns/}getctag'] = $this->getCTag();
        $response = [];
		
        foreach($properties as $propertyName) {

            if (isset($this->addressBookInfo[$propertyName])) {

                $response[$propertyName] = $this->addressBookInfo[$propertyName];

            }

        }
		
        return $response;

    }

	/* @param array $mutations
     * @return bool|array
     */
    public function updateProperties($mutations) {

        return false;

    }
	
	public function propPatch(\Sabre\DAV\PropPatch $propPatch) {
		
		return false;
		
	}
	
    /**
     * Returns the owner principal
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
    public function getOwner() {

		$iUserId = $this->getUser();
		return ($iUserId) ? 'principals/' . $iUserId : null;

    }

    /**
     * Returns a group principal
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
    public function getGroup() {

        return null;

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
    public function getACL() {

		$iUserId = $this->getUser();
        return [
            [
                'privilege' => '{DAV:}read',
                'principal' => ($iUserId) ? 'principals/' . $iUserId : null,
                'protected' => true,
            ],
        ];

    }

    /**
     * Updates the ACL
     *
     * This method will receive a list of new ACE's.
     *
     * @param array $acl
     * @return void
     */
    public function setACL(array $acl) {

        throw new \Sabre\DAV\Exception\MethodNotAllowed('Changing ACL is not yet supported');

    }

    /**
     * Returns the list of supported privileges for this node.
     *
     * The returned data structure is a list of nested privileges.
     * See Sabre\DAVACL\Plugin::getDefaultSupportedPrivilegeSet for a simple
     * standard structure.
     *
     * If null is returned from this method, the default privilege set is used,
     * which is fine for most common usecases.
     *
     * @return array|null
     */
    public function getSupportedPrivilegeSet() {

        return null;

    }	
}
