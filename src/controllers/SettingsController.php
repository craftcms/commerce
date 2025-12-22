<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Settings;
use craft\commerce\Plugin;
use craft\commerce\services\Subscriptions;
use craft\helpers\App;
use craft\helpers\StringHelper;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Class Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SettingsController extends BaseAdminController
{
    /**
     * Commerce Settings Form
     */
    public function actionEdit(): Response
    {
        $settings = Plugin::getInstance()->getSettings();

        $craftSettings = App::mailSettings();
        $settings->emailSenderAddressPlaceholder = $craftSettings['fromEmail'] ?? '';
        $settings->emailSenderNamePlaceholder = $craftSettings['fromName'] ?? '';

        $variables = [
            'settings' => $settings,
        ];

        return $this->renderTemplate('commerce/settings/general', $variables);
    }

    /**
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();
        $settings = $this->request->getBodyParam('settings');
        $pluginSettingsSaved = Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);

        if (!$pluginSettingsSaved) {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/settings/general/index', [
                'settings' => $plugin->getSettings(),
            ]);
        }

        $this->setSuccessFlash(Craft::t('commerce', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Saves the field layout.
     *
     * @return Response|null
     */
    public function actionSaveSubscriptionFieldLayout(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $configData = [StringHelper::UUID() => $fieldLayout->getConfig()];

        Craft::$app->getProjectConfig()->set(Subscriptions::CONFIG_FIELDLAYOUT_KEY, $configData);

        $this->setSuccessFlash(Craft::t('commerce', 'Subscription fields saved.'));

        return $this->redirectToPostedUrl();
    }
}
