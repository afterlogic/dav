<?php

declare(strict_types=1);

namespace Afterlogic\DAV\FS;

/**
 * The INode interface is the base interface, and the parent class of both ICollection and IFile.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
interface INode extends \Sabre\DAV\INode
{
    public function getRelativePath();
}