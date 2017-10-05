<?php

namespace craft\commerce\controllers;

use craft\commerce\models\Settings as SettingsModel;
use craft\commerce\Plugin;
use Craft;
use yii\web\Response;

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
    public function actionEdit(): Response
    {
        $settings = Plugin::getInstance()->getSettings();

        $craftSettings = Craft::$app->getSystemSettings()->getEmailSettings();
        $settings->emailSenderAddressPlaceholder = (isset($craftSettings['fromEmail']) ? $craftSettings['fromEmail'] : '');
        $settings->emailSenderNamePlaceholder = (isset($craftSettings['fromName']) ? $craftSettings['fromName'] : '');

        return $this->renderTemplate('commerce/settings/general', ['settings' => $settings]);
    }

    /**
     * @throws HttpException
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();
        $postData = Craft::$app->getRequest()->getParam('settings');
        $settings = new SettingsModel($postData);

        if (!Plugin::getInstance()->settings->saveSettings($settings)) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/settings', ['settings' => $settings]);
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Settings saved.'));
            $this->redirectToPostedUrl();
        }
    }
}
