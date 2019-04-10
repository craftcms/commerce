<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\base\Model;
use craft\commerce\elements\Subscription;
use craft\commerce\models\Address;
use craft\commerce\models\LiteSettings;
use craft\commerce\models\Settings as SettingsModel;
use craft\commerce\models\Settings;
use craft\commerce\models\TaxRate;
use craft\commerce\Plugin;
use craft\commerce\services\Subscriptions;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\i18n\Locale;
use yii\web\Response;

/**
 * Class Settings Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class SettingsController extends BaseAdminController
{
    // Public Methods
    // =========================================================================

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
            'settings' => $settings
        ];

        return $this->renderTemplate('commerce/settings/general', $variables);
    }

    /**
     * @return Response|null
     */
    public function actionSaveSettings()
    {
        $this->requirePostRequest();

        $params = Craft::$app->getRequest()->getBodyParams();
        $data = $params['settings'];

        $settings = Plugin::getInstance()->getSettings();
        $settings->emailSenderAddress = $data['emailSenderAddress'] ?? $settings->emailSenderAddress;
        $settings->emailSenderName = $data['emailSenderName'] ?? $settings->emailSenderName;
        $settings->weightUnits = $data['weightUnits'] ?? key($settings->getWeightUnitsOptions());
        $settings->dimensionUnits = $data['dimensionUnits'] ?? key($settings->getDimensionUnits());
        $settings->minimumTotalPriceStrategy = $data['minimumTotalPriceStrategy'] ?? Settings::MINIMUM_TOTAL_PRICE_STRATEGY_DEFAULT;
        $settings->orderPdfPath = $data['orderPdfPath'] ?? $settings->orderPdfPath;
        $settings->orderPdfFilenameFormat = $data['orderPdfFilenameFormat'] ?? $settings->orderPdfFilenameFormat;
        $settings->orderReferenceFormat = $data['orderReferenceFormat'] ?? $settings->orderReferenceFormat;

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/settings/general/index', compact('settings'));
        }

        $pluginSettingsSaved = Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings->toArray());

        if (!$pluginSettingsSaved) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/settings/general/index', compact('settings'));
        }

        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Saves the field layout.
     *
     * @return Response|null
     */
    public function actionSaveSubscriptionFieldLayout()
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $configData = [StringHelper::UUID() => $fieldLayout->getConfig()];

        Craft::$app->getProjectConfig()->set(Subscriptions::CONFIG_FIELDLAYOUT_KEY, $configData);

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Subscription fields saved.'));

        return $this->redirectToPostedUrl();
    }
}
