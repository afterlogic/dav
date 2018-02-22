<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\Principal\Backend;

use Sabre\DAV\MkCol;

class PDO extends \Sabre\DAVACL\PrincipalBackend\PDO 
{
    /**
     * Sets up the backend.
     */
    public function __construct() { } 

    /**
     * Returns a list of principals based on a prefix.
     *
     * This prefix will often contain something like 'principals'. You are only
     * expected to return principals that are in this base path.
     *
     * You are expected to return at least a 'uri' for every user, you can
     * return any additional properties if you wish so. Common properties are:
     *   {DAV:}displayname
     *   {http://sabredav.org/ns}email-address - This is a custom SabreDAV
     *     field that's actualy injected in a number of other properties. If
     *     you have an email address, use this property.
     *
     * @param string $prefixPath
     * @return array
     */
    function getPrincipalsByPrefix($prefixPath) {

        $principals = [];

		$bPrevState = \Aurora\System\Api::skipCheckUserRole(true);
		$aUsers = \Aurora\Modules\Core\Module::Decorator()->GetUserList(0, 0);
		
		\Aurora\System\Api::skipCheckUserRole($bPrevState);
		
		if (is_array($aUsers)) 
		{
			foreach ($aUsers as $oUser) 
			{
				$principals[] = array(
					'id' => $oUser['UUID'],
					'uri' => 'principals/'.$oUser['PublicId'],
//					'{http://sabredav.org/ns}email-address' => $oUser['Name'],
					'{DAV:}displayname' => $oUser['Name'],
				);
			}
		}

        return $principals;

    }

    /**
     * Returns a specific principal, specified by it's path.
     * The returned structure should be the exact same as from
     * getPrincipalsByPrefix.
     *
     * @param string $path
     * @return array
     */
    function getPrincipalByPath($path) {

        list(, $sUsername) = \Sabre\Uri\split($path);
		return array(
			'id' => $sUsername,
			'uri' => 'principals/'.$sUsername,
//			'{http://sabredav.org/ns}email-address' => $sUsername,
			'{DAV:}displayname' => $sUsername,
		);

    }

    /**
     * Updates one ore more webdav properties on a principal.
     *
     * The list of mutations is stored in a Sabre\DAV\PropPatch object.
     * To do the actual updates, you must tell this object which properties
     * you're going to process with the handle() method.
     *
     * Calling the handle method is like telling the PropPatch object "I
     * promise I can handle updating this property".
     *
     * Read the PropPatch documenation for more info and examples.
     *
     * @param string $path
     * @param DAV\PropPatch $propPatch
     */
    function updatePrincipal($path, \Sabre\DAV\PropPatch $propPatch) {

		return true;

    }

    /**
     * This method is used to search for principals matching a set of
     * properties.
     *
     * This search is specifically used by RFC3744's principal-property-search
     * REPORT.
     *
     * The actual search should be a unicode-non-case-sensitive search. The
     * keys in searchProperties are the WebDAV property names, while the values
     * are the property values to search on.
     *
     * By default, if multiple properties are submitted to this method, the
     * various properties should be combined with 'AND'. If $test is set to
     * 'anyof', it should be combined using 'OR'.
     *
     * This method should simply return an array with full principal uri's.
     *
     * If somebody attempted to search on a property the backend does not
     * support, you should simply return 0 results.
     *
     * You can also just return 0 results if you choose to not support
     * searching at all, but keep in mind that this may stop certain features
     * from working.
     *
     * @param string $prefixPath
     * @param array $searchProperties
     * @param string $test
     * @return array
     */
    function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof') {

        $aPrincipals = [];

		if (isset($searchProperties['{http://sabredav.org/ns}email-address'])) {
			
			$oUsersManager = \Aurora\System\Api::GetSystemManager('users');
			$oAccount = $oUsersManager->getAccountByEmail(
					$searchProperties['{http://sabredav.org/ns}email-address']
			);
			if ($oAccount instanceof \CAccount) {
				
	            $aPrincipals[] = \Afterlogic\DAV\Constants::PRINCIPALS_PREFIX . '/' . $oAccount->Email;
			}
		}
		
        return $aPrincipals;

    }

    /**
     * Returns the list of members for a group-principal
     *
     * @param string $principal
     * @return array
     */
    function getGroupMemberSet($principal) {

		return [];

    }

    /**
     * Returns the list of groups a principal is a member of
     *
     * @param string $principal
     * @return array
     */
    function getGroupMembership($principal) {

        return [];

    }

    /**
     * Updates the list of group members for a group principal.
     *
     * The principals should be passed as a list of uri's.
     *
     * @param string $principal
     * @param array $members
     * @return void
     */
    function setGroupMemberSet($principal, array $members) {

    }

    /**
     * Creates a new principal.
     *
     * This method receives a full path for the new principal. The mkCol object
     * contains any additional webdav properties specified during the creation
     * of the principal.
     *
     * @param string $path
     * @param MkCol $mkCol
     * @return void
     */
    function createPrincipal($path, MkCol $mkCol) {

    }

}
