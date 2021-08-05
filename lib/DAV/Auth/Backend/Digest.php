<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\Auth\Backend;

use Sabre\DAV;
use Sabre\HTTP;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Digest extends \Sabre\DAV\Auth\Backend\AbstractDigest
{

	public function getDigestHash($sRealm, $sUserName)
	{
		if (class_exists('\\Aurora\\System\\Api') && \Aurora\System\Api::IsValid() && $sUserName !== \Afterlogic\DAV\Constants::DAV_PUBLIC_PRINCIPAL && $sUserName !== \Afterlogic\DAV\Constants::DAV_TENANT_PRINCIPAL)
		{
			return \Aurora\Modules\Core\Module::Decorator()->GetDigestHash($sUserName, $sRealm, \Aurora\Modules\Mail\Models\MailAccount::class);
		}

		return null;
	}

	function check(RequestInterface $request, ResponseInterface $response)
	{
		$aResult = parent::check($request, $response);

		if ($aResult[0] === true)
		{
			$sLogin = \str_replace($this->principalPrefix, '', $aResult[1]);

			if ($sLogin)
			{
				$oAccount = \Aurora\Modules\Core\Module::Decorator()->GetAccountUsedToAuthorize($sLogin);
				if ($oAccount)
				{
					$sAuthToken = \Aurora\System\Api::UserSession()->Set(
						\Aurora\System\UserSession::getTokenData($oAccount, true),
						0
					);

					\Aurora\Api::authorise($sAuthToken);
				}
			}
		}

		return $aResult;
	}
}
