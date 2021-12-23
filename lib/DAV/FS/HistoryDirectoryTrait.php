<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\FS;

use Afterlogic\DAV\Server;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
trait HistoryDirectoryTrait
{
    public function getHistoryDirectory()
	{
        $oNode = null;
        if ($this instanceof File) {
            list(, $owner) = \Sabre\Uri\split($this->getOwner());
            Server::getInstance()->setUser($owner);
            try {
                $oNode = Server::getNodeForPath('files/'. $this->getStorage() . $this->getRelativePath() . '/' . $this->getName() . '.hist');
            }
            catch (\Exception $oEx) {}
        }
		return $oNode;
	}

	public function deleteHistoryDirectory()
	{
		$oNode = $this->getHistoryDirectory();

		if ($oNode instanceof Directory)
		{
			$oNode->delete();
		}

	}
}