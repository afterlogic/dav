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

use Sabre\Uri;

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

        $parts = explode('/', $path);
        $node = $this->rootNode;

        while (count($parts)) {
            if (!($node instanceof ICollection)) {
                throw new NotFound('Could not find node at path: '.$path);
            }

            if ($node instanceof \Sabre\DAV\INodeByPath) {
                $targetNode = $node->getNodeForPath(implode('/', $parts));
                if ($targetNode instanceof \Sabre\DAV\Node) {
                    $node = $targetNode;
                    break;
                }
            }

            $part = array_shift($parts);
            if ('' !== $part) {
                $node = $node->getChild($part);
            }
        }

        $this->cache[Server::getUser()][$path] = $node;

        return $node;
    }

    public function getMultipleNodes($paths)
    {
        // Finding common parents
        $parents = [];
        foreach ($paths as $path) {
            list($parent, $node) = Uri\split($path);
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
