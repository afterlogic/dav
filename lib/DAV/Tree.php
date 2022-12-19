<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV;

use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\ICollection;
use Sabre\DAV\IMultiGet;

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
    public function getNodeForPath($path)
    {
        $path = trim($path, '/');

        if (isset($this->cache[Server::getUser()][$path])) {
            return $this->cache[Server::getUser()][$path];
        }

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

            if (!($parent instanceof ICollection)) {
                throw new NotFound('Could not find node at path: ' . $path);
            }

            $node = $parent->getChild($baseName);
        }

        $this->cache[Server::getUser()][$path] = $node;

        return $node;
    }

    public function getMultipleNodes($paths)
    {
        // Finding common parents
        $parents = [];
        foreach ($paths as $path) {
            list($parent, $node) = split($path);
            if (!isset($parents[$parent])) {
                $parents[$parent] = [$node];
            } else {
                $parents[$parent][] = $node;
            }
        }

        $result = [];

        foreach ($parents as $parent => $children) {
            $parentNode = $this->getNodeForPath($parent);
            if ($parentNode instanceof IMultiGet) {
                foreach ($parentNode->getMultipleChildren($children) as $childNode) {
                    $fullPath = $parent.'/'.$childNode->getName();
                    $result[$fullPath] = $childNode;
                    $this->cache[Server::getUser()][$fullPath] = $childNode;
                }
            } else {
                foreach ($children as $child) {
                    $fullPath = $parent.'/'.$child;
                    $result[$fullPath] = $this->getNodeForPath($fullPath);
                }
            }
        }

        return $result;
    }

    public function getChildren($path)
    {
        $node = $this->getNodeForPath($path);
        $basePath = trim($path, '/');
        if ('' !== $basePath) {
            $basePath .= '/';
        }

        $children = [];
        if ($node instanceof ICollection) {
            $children = $node->getChildren();
        }

        foreach ($children as $child) {
            $this->cache[Server::getUser()][$basePath.$child->getName()] = $child;
            yield $child;
        }
    }

    public function markDirty($path)
    {
        // We don't care enough about sub-paths
        // flushing the entire cache
        $path = trim($path, '/');
        foreach ($this->cache[Server::getUser()] as $nodePath => $node) {
            if ('' === $path || $nodePath == $path || 0 === strpos((string) $nodePath, $path.'/')) {
                unset($this->cache[Server::getUser()][$nodePath]);
            }
        }
    }
}
