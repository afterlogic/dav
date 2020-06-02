<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\CardDAV\Shared;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Card extends \Sabre\CardDAV\Card {

   protected $principalUri;

	/**
     * Constructor
     *
     * @param \Sabre\CardDAV\Backend\BackendInterface $carddavBackend
     * @param array $addressBookInfo
     * @param array $cardData
     */
    public function __construct(\Sabre\CardDAV\Backend\BackendInterface $carddavBackend,array $addressBookInfo,array $cardData,$principalUri) {

        parent::__construct($carddavBackend, $addressBookInfo, $cardData);
		$this->principalUri = $principalUri;

    }

    public function getACL() {

        return array(
            array(
                'privilege' => '{DAV:}read',
                'principal' => $this->principalUri,
                'protected' => true,
            ),
            array(
                'privilege' => '{DAV:}write',
                'principal' => $this->principalUri,
                'protected' => true,
            ),
        );

    }
 }
