<?php
namespace Craft;

/**
 * Class Commerce_SettingsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
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
        craft()->request->redirect('settings/general');
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

        if (!craft()->commerce_settings->save($settings)) {
            craft()->userSession->setError(Craft::t('Error, Commerce settings not saved.'));
            $this->renderTemplate('commerce/settings', ['settings' => $settings]);
        } else {
            craft()->userSession->setNotice(Craft::t('Success, Commerce settings saved.'));
            $this->redirectToPostedUrl();
        }
    }
}