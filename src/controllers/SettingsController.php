<?php

namespace craft\commerce\controllers;

;

use craft\commerce\models\Settings as SettingsModel;

/**
 * Class Settings Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class SettingsController extends BaseAdminController
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
        $settings = Plugin::getInstance()->getSettings();

        $craftSettings = Craft::$app->getEmail()->getSettings();
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
        $postData = Craft::$app->getRequest()->getParam('settings');
        $settings = new SettingsModel($postData);

        if (!Plugin::getInstance()->getSettings()->saveSettings($settings)) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save settings.'));
            $this->renderTemplate('commerce/settings', ['settings' => $settings]);
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Settings saved.'));
            $this->redirectToPostedUrl();
        }
    }
}
