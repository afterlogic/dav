<?php

namespace Afterlogic\DAV;

/**
 * SimpleCollection
 *
 * The SimpleCollection is used to quickly setup static directory structures.
 * Just create the object with a proper name, and add children to use it.
 *
 * @copyright Copyright (C) fruux GmbH (https://fruux.com/)
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class SimpleCollection extends \Sabre\DAV\SimpleCollection {

     function deleteChild($childName) {

        unset($this->children[$childName]);

    }
}
