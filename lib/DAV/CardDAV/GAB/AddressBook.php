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
	 * @var string
     */
	private $sUserPublicId;

	/**
     * Constructor
     */
    public function __construct($name, $displayname = '')
	{
		$this->name = $name;
		$this->sUserPublicId = null;

		$this->addressBookInfo['{DAV:}displayname'] = (empty($displayname)) ? $name : $displayname;
    }


	public function getUser()
	{
		if ($this->sUserPublicId == null) {

			$this->sUserPublicId = \Afterlogic\DAV\Server::getUser();
		}
		return $this->sUserPublicId;
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

		$oContact = \Aurora\System\Managers\Eav::getInstance()->getEntity(
			$aPathInfo['filename'],
			\Aurora\Modules\Contacts\Classes\Contact::class
		);
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

		if (!$this->isEnabled())
		{
			return $aCards;
		}

		$iIdTenant = 0;
		$oUser = \Aurora\Modules\Core\Module::getInstance()->GetUserByPublicId($this->getUser());
		if ($oUser)
		{
			$iIdTenant = $oUser->IdTenant;
		}

		$aContacts = (new \Aurora\System\EAV\Query(\Aurora\Modules\Contacts\Classes\Contact::class))
			->select(['LastName', 'FirstName', 'FullName', 'ViewEmail', 'DateModified'])
			->where(['Storage' => 'team', 'IdTenant' => $iIdTenant])
			->asArray()
			->exec();

		if (is_array($aContacts) && count($aContacts) > 0)
		{
			foreach($aContacts as $aContact) {
				$sFirstName = isset($aContact['FirstName']) ? $aContact['FirstName'] : '';
				$sLastName = isset($aContact['LastName']) ? $aContact['LastName'] : '';
				$sFullName = isset($aContact['FullName']) ? $aContact['FullName'] : '';
				$aName = [$sLastName, $sFirstName];
				$vCard = new \Sabre\VObject\Component\VCard(
					[
						'VERSION' => '3.0',
						'UID' => $aContact['UUID'],
						'FN' => $sFullName,
						'N' => (empty($sLastName) && empty($sFirstName)) ? explode(' ', $sFullName) : $aName
					]
				);

				$vCard->add(
					'EMAIL',
					$aContact['ViewEmail'],
					[
						'type' => ['work'],
						'pref' => 1,
					]
				);

				$aCards[] = new Card(
					[
						'uri' => $aContact['UUID'] . '.vcf',
						'carddata' => $vCard->serialize(),
						'lastmodified' => isset($aContact['DateModified']) ? strtotime($aContact['DateModified']) : time()
					]
				);
			}
		}
        return $aCards;
    }

    public function getCTag() {

		$iResult = (new \Aurora\System\EAV\Query(\Aurora\Modules\Contacts\Classes\Contact::class))
			->where(['Storage' => 'team'])
			->count()
			->exec();

		$aResult = (new \Aurora\System\EAV\Query(\Aurora\Modules\Contacts\Classes\Contact::class))
			->select(['DateModified'])
			->where(['Storage' => 'team'])
			->offset(0)
			->limit(1)
			->one()
			->asArray()
			->exec();

		if (!empty($aResult['DateModified']))
		{
			$iResult .= strtotime($aResult['DateModified']);
		}

		return $iResult;
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

		$sUserPublicId = $this->getUser();
		return ($sUserPublicId) ? 'principals/' . $sUserPublicId : null;

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

		$sUserPublicId = $this->getUser();
        return [
            [
                'privilege' => '{DAV:}read',
                'principal' => ($sUserPublicId) ? 'principals/' . $sUserPublicId : null,
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

	public function isEnabled()
	{
		$oTenant = \Afterlogic\DAV\Server::getTenantObject();
		$oUser = \Afterlogic\DAV\Server::getUserObject();
		$bIsModuleDisabledForTenant = isset($oTenant) ? $oTenant->isModuleDisabled('TeamContacts') : false;
		$bIsModuleDisabledForUser = isset($oUser) ? $oUser->isModuleDisabled('TeamContacts') : false;

		return !($bIsModuleDisabledForTenant || $bIsModuleDisabledForUser);
	}
}
