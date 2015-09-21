<?php
namespace Craft;

/**
 * Class Market_SettingsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_SettingsController extends Market_BaseController
{

	/**
	 * Market Settings Index
	 */
	public function actionIndex ()
	{
		craft()->request->redirect('settings/global');
	}

	/**
	 * Market Settings Form
	 */
	public function actionEdit ()
	{
		$this->requireAdmin();

		$settings = craft()->market_settings->getSettings();

		$craftSettings = craft()->email->getSettings();
		$settings->emailSenderAddressPlaceholder = (isset($craftSettings['emailAddress']) ? $craftSettings['emailAddress'] : '');
		$settings->emailSenderNamePlaceholder = (isset($craftSettings['senderName']) ? $craftSettings['senderName'] : '');

		$this->renderTemplate('market/settings/global',
			['settings' => $settings]);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSaveSettings ()
	{
		$this->requireAdmin();

		$this->requirePostRequest();
		$postData = craft()->request->getPost('settings');
		$settings = Market_SettingsModel::populateModel($postData);

		if (!craft()->market_settings->save($settings))
		{
			craft()->userSession->setError(Craft::t('Error, Market settings not saved.'));
			$this->renderTemplate('market/settings', ['settings' => $settings]);
		}
		else
		{
			craft()->userSession->setNotice(Craft::t('Success, Market settings saved.'));
			$this->redirectToPostedUrl();
		}
	}
}