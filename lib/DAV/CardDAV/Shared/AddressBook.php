<?php

/* -AFTERLOGIC LICENSE HEADER- */

namespace Afterlogic\DAV\CardDAV\Shared;

class AddressBook extends \Afterlogic\DAV\CardDAV\AddressBook {
    
	protected $principalUri;
	
	/**
     * Constructor
     *
     * @param Backend\BackendInterface $carddavBackend
     * @param array $addressBookInfo
     */
    public function __construct(\Sabre\CardDAV\Backend\BackendInterface $carddavBackend, array $addressBookInfo, $principalUri) {
        
		parent::__construct($carddavBackend, $addressBookInfo);
		$this->principalUri = $principalUri;
		
    }	
	
    function getChildACL() {

        return [
            [
                'privilege' => '{DAV:}all',
                'principal' => $this->principalUri,
                'protected' => true,
            ],
        ];

    }	
	
    function getOwner() {

        return $this->principalUri;

    }	
	
   /**
     * Returns a card
     *
     * @param string $name
     * @return Card
     */
    function getChild($name) {

        $obj = $this->carddavBackend->getCard($this->addressBookInfo['id'], $name);
        if (!$obj) throw new \Sabre\DAV\Exception\NotFound('Card not found');
        return new Card($this->carddavBackend, $this->addressBookInfo, $obj, $this->principalUri);

    }

    /**
     * Returns the full list of cards
     *
     * @return array
     */
    function getChildren() {

        $objs = $this->carddavBackend->getCards($this->addressBookInfo['id']);
        $children = [];
        foreach ($objs as $obj) {
            $obj['acl'] = $this->getChildACL();
            $children[] = new Card($this->carddavBackend, $this->addressBookInfo, $obj, $this->principalUri);
        }
        return $children;

    }	
	
    /**
     * This method receives a list of paths in it's first argument.
     * It must return an array with Node objects.
     *
     * If any children are not found, you do not have to return them.
     *
     * @param string[] $paths
     * @return array
     */
    function getMultipleChildren(array $paths) {

        $objs = $this->carddavBackend->getMultipleCards($this->addressBookInfo['id'], $paths);
		
        $children = [];
        foreach ($objs as $obj) {
            $obj['acl'] = $this->getChildACL();
            $children[] = new Card($this->carddavBackend, $this->addressBookInfo, $obj, $this->principalUri);
        }
        return $children;

	}	
}
