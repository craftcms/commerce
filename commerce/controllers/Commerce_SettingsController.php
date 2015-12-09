<?php
namespace Craft;

/**
 * Class Commerce_SettingsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_SettingsController extends Commerce_BaseAdminController
{

    /**
     * Commerce Settings Index
     */
    public function actionIndex()
    {
        $this->redirect('commerce/settings/general');
    }

    /**
     * Commerce Settings Form
     */
    public function actionEdit()
    {
        $settings = craft()->commerce_settings->getSettings();

        $craftSettings = craft()->email->getSettings();
        $settings->emailSenderAddressPlaceholder = (isset($craftSettings['emailAddress']) ? $craftSettings['emailAddress'] : '');
        $settings->emailSenderNamePlaceholder = (isset($craftSettings['senderName']) ? $craftSettings['senderName'] : '');

        $this->renderTemplate('commerce/settings/general',
            ['settings' => $settings]);
    }

    /**
     * @throws HttpException
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();
        $postData = craft()->request->getPost('settings');
        $settings = Commerce_SettingsModel::populateModel($postData);

        if (!craft()->commerce_settings->saveSettings($settings)) {
            craft()->userSession->setError(Craft::t('Couldn’t save settings.'));
            $this->renderTemplate('commerce/settings', ['settings' => $settings]);
        } else {
            craft()->userSession->setNotice(Craft::t('Settings saved.'));
            $this->redirectToPostedUrl();
        }
    }
}
