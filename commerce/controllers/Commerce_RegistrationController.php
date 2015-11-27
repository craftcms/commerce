<?php
namespace Craft;

/**
 * Class Commerce_RegistrationController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_RegistrationController extends Commerce_BaseAdminController
{
	public function actionEdit()
	{
	    $licenseKey = craft()->plugins->getPluginLicenseKey('Commerce');
	    $licenseKeyStatus = craft()->plugins->getPluginLicenseKeyStatus('Commerce');

	    if ($licenseKey) {
	    	$licenseKey = rtrim(chunk_split($licenseKey, 4, '-'), '-');;
	    }

	    $this->renderTemplate('commerce/settings/registration', [
	        'licenseKey' => $licenseKey,
	        'licenseKeyStatus' => $licenseKeyStatus,
	    ]);
	}

	public function actionUnregister()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		// TODO: call the unregisterPlugin Elliott endpoint

		craft()->plugins->setPluginLicenseKey('Commerce', null);

		$this->_sendSuccessResponse();
	}

	public function actionUpdateLicenseKey()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$licenseKey = craft()->request->getRequiredPost('licenseKey');

		// TODO: call the registerPlugin Elliott endpoint

		try {
			craft()->plugins->setPluginLicenseKey('Commerce', $licenseKey ?: null);
		} catch (InvalidLicenseKeyException $e) {
			$this->returnErrorJson(Craft::t('That license key is invalid.'));
		}

		if ($licenseKey) {
			craft()->plugins->setPluginLicenseKeyStatus('Commerce', LicenseKeyStatus::Valid);
		} else {
			craft()->plugins->setPluginLicenseKeyStatus('Commerce', LicenseKeyStatus::Unknown);
		}

		$this->_sendSuccessResponse();
	}

	public function actionTransfer()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		// TODO: call the transferPlugin Elliott endpoint

		craft()->plugins->setPluginLicenseKeyStatus('Commerce', LicenseKeyStatus::Valid);

		$this->_sendSuccessResponse();
	}

	/**
	 * Returns a successful license update response.
	 */
	private function _sendSuccessResponse()
	{
		$this->returnJson([
			'success' => true,
			'licenseKey' => craft()->plugins->getPluginLicenseKey('Commerce'),
			'licenseKeyStatus' => craft()->plugins->getPluginLicenseKeyStatus('Commerce'),
		]);
	}
}
