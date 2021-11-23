<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Afterlogic\DAV\Auth;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2019, Afterlogic Corp.
 */
class Backend
{
	/**
	 *
	 */
	public static function Login($sUserName, $sPassword)
	{
		$mResult = false;

		$oDavModule = \Aurora\System\Api::GetModuleDecorator('Dav');
		if ($oDavModule)
		{
			try
			{
				$mResult = $oDavModule->Login($sUserName, $sPassword);

				if (isset($mResult['AuthToken']))
				{
					$mResult = \Aurora\System\Api::getAuthenticatedUserPublicId($mResult['AuthToken']);
				}
				else
				{
					$mResult = false;
				}
			}
			catch (\Exception $ex)
			{
				\Aurora\System\Api::LogException($ex);
				$mResult = false;
			}
		}

		return $mResult;
	}

}
