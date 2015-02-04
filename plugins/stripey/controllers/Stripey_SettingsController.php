<?php
namespace Craft;

class Stripey_SettingsController extends Stripey_BaseController
{

	/**
	 * Stripey Settings Form
	 */
	public function actionEdit()
	{
		$settings = craft()->stripey_settings->getSettings();
		$settings = Stripey_SettingsModel::populateModel($settings);

		$this->renderTemplate('stripey/settings', array(
			'settings' => craft()->stripey_settings->getSettings()
		));
	}

	public function actionSaveSettings()
	{
		$this->requirePostRequest();
		$postData = craft()->request->getPost('settings');
		$settings = Stripey_SettingsModel::populateModel($postData);

		if (!$settings->validate()) {
			craft()->userSession->setError(Craft::t('Error, Stripey settings not saved.'));
			$this->renderTemplate('stripey/settings', array(
				'settings' => $settings
			));
		} else {
			craft()->stripey_settings->setSettings($settings);
			craft()->userSession->setNotice(Craft::t('Success, Stripey settings saved.'));
			$this->redirectToPostedUrl();
		}
	}
} 