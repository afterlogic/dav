<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV;

use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;

use function Sabre\Uri\split;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */

class Tree extends \Sabre\DAV\Tree
{
    /* Returns the INode object for the requested path
    *
    * @param string $path
    * @return INode
    */
   function getNodeForPath($path) {

       $path = trim($path, '/');

       // Is it the root node?
       if (!strlen($path)) {
           return $this->rootNode;
       }

       // Attempting to fetch its parent
       list($parentName, $baseName) = split($path);

       // If there was no parent, we must simply ask it from the root node.
       if ($parentName === "") {
           $node = $this->rootNode->getChild($baseName);
       } else {
           // Otherwise, we recursively grab the parent and ask him/her.
           $parent = $this->getNodeForPath($parentName);

           if (!($parent instanceof ICollection))
               throw new NotFound('Could not find node at path: ' . $path);

           $node = $parent->getChild($baseName);

       }

       return $node;

   }

}