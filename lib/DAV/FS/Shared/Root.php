<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class Root extends \Afterlogic\DAV\FS\Personal\Root implements \Sabre\DAVACL\IACL {
	
	protected $pdo = null;
	
	public function __construct() {
		
		$this->getUser();

		$this->pdo = new \Afterlogic\DAV\FS\Backend\PDO();
	}
	
	public function getName() {

        return \Aurora\System\Enums\FileStorageType::Shared;

	}	

    /**
     * Returns the owner principal.
     *
     * This must be a url to a principal, or null if there's no owner
     *
     * @return string|null
     */
	public function getOwner()
	{
		return 'principals/' . $this->UserPublicId;
	}
	
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
		return [
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->getOwner(),
                'protected' => true,
            ],
        ];
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
	
	protected function populateItem($aSharedFile)
	{
		$mResult = false;

		if (is_array($aSharedFile))
		{
			$sRootPath = \Afterlogic\DAV\FS\Plugin::getStoragePath(
				basename($aSharedFile['owner']), 
				$aSharedFile['storage']
			);
			
			$path = $sRootPath . '/' . trim($aSharedFile['path'], '/');
					
			if ($aSharedFile['isdir'])
			{
				$mResult = new Directory(
					$aSharedFile['owner'],
					$aSharedFile['principaluri'],
					$aSharedFile['storage'],
					$path,
					$aSharedFile['access'],
					$aSharedFile['uid'],
					true
				);
			}
			else
			{
				$mResult = new File(
					$aSharedFile['owner'],
					$aSharedFile['principaluri'],
					$aSharedFile['storage'],
					$path,
					$aSharedFile['access'],
					$aSharedFile['uid'],
					true
				);
			}
		}
		return $mResult;		
	}
	
    public function getChild($name) {

		$aSharedFile = $this->pdo->getSharedFileByUid('principals/' . $this->UserPublicId, $name);

		return $this->populateItem($aSharedFile);
		
    }	
	
	public function getChildren() {

		$aResult = [];
		
		$aSharedFiles = $this->pdo->getSharedFilesForUser('principals/' . $this->UserPublicId);

		foreach ($aSharedFiles as $aSharedFile)
		{
			$oSharedItem = $this->populateItem($aSharedFile);
			if ($oSharedItem)	
			{
				$aResult[] = $oSharedItem;
			}
		}
		
		return $aResult;

    }	
	
}
