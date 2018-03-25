<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CardDAV\GAB;

class AddressBooks extends \Sabre\DAV\Collection implements \Sabre\CardDAV\IDirectory, \Sabre\DAV\IProperties, \Sabre\DAVACL\IACL {

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

    /**
     * @return array
     */
    public function getChildren()
	{
        $aCards = array();
		$aContacts = array();

		$oContactsDecorator = /* @var $oContactsDecorator \Aurora\Modules\Contacts\Module */ \Aurora\Modules\Contacts\Module::Decorator();
		
		if ($oContactsDecorator)
		{
			$aContacts = $oContactsDecorator->GetContacts(
				'team', 
				0, 
				9999, 
				\Aurora\Modules\Contacts\Enums\SortField::Email, 
				\Aurora\System\Enums\SortOrder::ASC
			);
		}

		foreach($aContacts['List'] as $aContact) {

			$aName = [$aContact['LastName'], $aContact['FirstName']];
			$vCard = new \Sabre\VObject\Component\VCard(
				array(
					'VERSION' => '3.0',
					'UID' => $aContact['UUID'],
					'FN' => $aContact['FullName'],
					'N' => (empty($aContact['LastName']) && empty($aContact['FirstName'])) ? explode(' ', $aContact['FullName']) : $aName
				)
			);

			$vCard->add(
				'EMAIL',
				$aContact['ViewEmail'],
				array(
					'type' => array(
						'work'
					),
					'pref' => 1,
				)
			);				

			$aCards[] = new Card(
				array(
					'uri' => $aContact['UUID'] . '.vcf',
					'carddata' => $vCard->serialize(),
					'lastmodified' => strtotime($aContact['DateModified'])
				)
			);
		}
        return $aCards;
    }

    public function getProperties($properties) {

        $response = array();
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
        return array(
            array(
                'privilege' => '{DAV:}read',
                'principal' => ($iUserId) ? 'principals/' . $iUserId : null,
                'protected' => true,
            ),
        );

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

        throw new DAV\Exception\MethodNotAllowed('Changing ACL is not yet supported');

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
