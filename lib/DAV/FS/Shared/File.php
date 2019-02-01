<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\FS\Shared;

class File extends \Afterlogic\DAV\FS\File implements \Sabre\DAVACL\IACL {
	
	protected $owner;
    protected $principalUri;
    protected $storage;
	protected $access;
	protected $uid;
	protected $inRoot;

    public function __construct($owner, $principalUri, $storage, $path, $access, $uid = null, $inRoot = false) {

        $this->owner = $owner;
        $this->principalUri = $principalUri;
        $this->storage = $storage;
        $this->access = $access;
        $this->uid = $uid;
        $this->inRoot = $inRoot;
        
        parent::__construct($path);
        
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
		$acl = [
            [
                'privilege' => '{DAV:}read',
                'principal' => $this->getOwner(),
                'protected' => true,
            ],
        ];

        if ($this->access == 1) {
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
    
    public function getStorage() {

        return $this->storage;

    }

	public function getAccess() {

        return $this->access;

    }

    public function getName() {

        list(, $name) = \Sabre\Uri\split($this->getPath());
        return isset($this->uid) ? $this->uid : $name;

    }	

    public function getId()
    {
        return $this->getName();
    }

    public function getDisplayName()
	{
        list(, $name) = \Sabre\Uri\split($this->getPath());
        return $name;
	}

	public function getRelativePath() 
	{

        list(, $owner) = \Sabre\Uri\split($this->getOwner());
        list($dir,) = \Sabre\Uri\split($this->getPath());

		return \str_replace(
            $this->getRootPath(), 
            '', 
            $dir
        );

    }    

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getLastModified() {

        if (\file_exists($this->path))
        {
            return \filemtime($this->path);
        }
        else
        {
            return null;
        }

    }    

    /**
     * Returns the last modification time, as a unix timestamp
     *
     * @return int
     */
    function getSize() {

        if (\file_exists($this->path))
        {
            return \filesize($this->path);
        }
        else
        {
            return null;
        }

    }        

    function delete()
    {
        if ($this->inRoot)
        {
            $pdo = new \Afterlogic\DAV\FS\Backend\PDO();
            $pdo->deleteShare($this->principalUri, $this->getId());
        }
        else
        {
            parent::delete();
        }
    }

}

