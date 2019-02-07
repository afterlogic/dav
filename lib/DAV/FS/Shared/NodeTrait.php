<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

trait NodeTrait 
{
     /**
     * Returns a group principal.
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
	public function getGroup()
	{
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
	public function getACL()
	{
		$acl = [
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->getOwner(),
                'protected' => true,
            ],
        ];
        if ($this->inRoot || $this->access == 1) {
            $acl[] = [
                'privilege' => '{DAV:}write',
                'principal' => $this->getOwner(),
                'protected' => true,
            ];
        }

        return $acl;        

	}
	
    /**
     * Updates the ACL.
     *
     * This method will receive a list of new ACE's as an array argument.
     *
     * @param array $acl
     */
	public function setACL(array $acl)
	{

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
	public function getSupportedPrivilegeSet()
	{
		return null;
	}

    public function getOwner() {

        return $this->principalUri;

    }
   
}
