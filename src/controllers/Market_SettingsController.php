<?php
namespace Craft;

class Market_SettingsController extends Market_BaseController
{

	/**
	 * Market Settings Form
	 */
	public function actionEdit()
	{
		$settings = craft()->market_settings->getSettings();
		$settings = Market_SettingsModel::populateModel($settings);

		$this->renderTemplate('market/settings', array(
			'settings' => craft()->market_settings->getSettings()
		));
	}

	public function actionSaveSettings()
	{
		$this->requirePostRequest();
		$postData = craft()->request->getPost('settings');
		$settings = Market_SettingsModel::populateModel($postData);

		if (!$settings->validate()) {
			craft()->userSession->setError(Craft::t('Error, Market settings not saved.'));
			$this->renderTemplate('market/settings', array(
				'settings' => $settings
			));
		} else {
			craft()->market_settings->setSettings($settings);
			craft()->userSession->setNotice(Craft::t('Success, Market settings saved.'));
			$this->redirectToPostedUrl();
		}
	}
} 