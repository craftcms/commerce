<?php
namespace Craft;


class Stripey_SettingsController extends Stripey_BaseController
{

    /**
     * Stripey Settings Form
     */
    public function actionEdit()
    {
        $settings = $this->plugin->getSettings();
        //$settings = array('secretKey'=>'sk_test_8Lvmi5qDkbHRLCsyexhvOGuj','publishableKey'=>'pk_test_ysElKNu1n56ehhFioJqVK2DJ');
        $settings = Stripey_SettingsModel::populateModel($settings);

        $this->plugin->setSettings($settings);

        $this->renderTemplate('stripey/settings', array(
            'settings' => $this->plugin->getSettings()
        ));
    }

    public function actionSaveSettings()
    {
        $this->requirePostRequest();
        $data     = craft()->request->getPost('settings');
        $settings = Stripey_SettingsModel::populateModel($data);

        if (!$settings->validate()) {
            craft()->userSession->setError(Craft::t('Error, Stripey settings not saved.'));
            $this->renderTemplate('stripey/settings', array(
                'settings' => $settings
            ));
        } else {
            craft()->plugins->savePluginSettings($this->plugin, $settings);
            craft()->userSession->setNotice(Craft::t('Success, Stripey settings saved.'));
            $this->redirectToPostedUrl();
        }


    }
} 