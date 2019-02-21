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

        $lite = new LiteSettings([
            'shippingBaseRate' => 0,
            'shippingPerItemRate' => 0,
            'taxRate' => 0,
            'taxName' => 'Tax',
            'taxInclude' => false,
        ]);

        if (Plugin::getInstance()->is(Plugin::EDITION_LITE)) {
            $shippingMethod = Plugin::getInstance()->getShippingMethods()->getLiteShippingMethod();
            $shippingRule = Plugin::getInstance()->getShippingRules()->getLiteShippingRule();
            $taxRate = Plugin::getInstance()->getTaxRates()->getLiteTaxRate();
            $lite->shippingBaseRate = $shippingRule->getBaseRate();
            $lite->shippingPerItemRate = $shippingRule->getPerItemRate();
            $lite->taxName = $taxRate->name;
            $lite->taxRate = $taxRate->rate;
            $lite->taxInclude = $taxRate->include;
        }

        $variables = [
            'settings' => $settings,
            'lite' => $lite
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
        $settings->orderPdfPath = $data['orderPdfPath'] ?? $settings->orderPdfPath;
        $settings->orderPdfFilenameFormat = $data['orderPdfFilenameFormat'] ?? $settings->orderPdfFilenameFormat;
        $settings->orderReferenceFormat = $data['orderReferenceFormat'] ?? $settings->orderReferenceFormat;

        $liteValid = true;
        if (Plugin::getInstance()->is(Plugin::EDITION_LITE)) {
            $lite = new LiteSettings();
            $lite->shippingPerItemRate = Craft::$app->getRequest()->getBodyParam('lite.shippingPerItemRate');
            $lite->shippingBaseRate = Craft::$app->getRequest()->getBodyParam('lite.shippingBaseRate');
            $lite->taxName = Craft::$app->getRequest()->getBodyParam('lite.taxName');
            $lite->taxInclude = (bool) Craft::$app->getRequest()->getBodyParam('lite.taxInclude');

            $percentSign = Craft::$app->getLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
            $rate = Craft::$app->getRequest()->getBodyParam('lite.taxRate');
            if (strpos($rate, $percentSign) || $rate >= 1) {
                $lite->taxRate = (float)$rate / 100;
            } else {
                $lite->taxRate = (float)$rate;
            }

            $liteValid = $lite->validate();
        }

        if (!$settings->validate() || !$liteValid) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/settings/general/index', compact('settings', 'lite'));
        }

        $pluginSettingsSaved = Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings->toArray());

        if (!$pluginSettingsSaved) {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save settings.'));
            return $this->renderTemplate('commerce/settings/general/index', compact('settings', 'lite'));
        }

        if (Plugin::getInstance()->is(Plugin::EDITION_LITE)) {
            $liteConfigSaved = $this->_saveLiteSettings($lite);

            if (!$liteConfigSaved) {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save shipping or tax settings.'));
                return $this->renderTemplate('commerce/settings/general/index', compact('settings', 'lite'));
            }
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

    /**
     * @return Response
     */
    public function actionEditLocation(): Response
    {
        $storeLocation = Plugin::getInstance()->getAddresses()->getStoreLocationAddress();

        if (!$storeLocation) {
            $storeLocation = new Address();
        }

        $variables = [
            'storeLocation' => $storeLocation
        ];

        return $this->renderTemplate('commerce/settings/location/index', $variables);
    }


    /**
     * Saves the store location setting
     */
    public function actionSaveStoreLocation()
    {
        $this->requirePostRequest();

        $id = (int)Craft::$app->getRequest()->getBodyParam('id');

        $address = Plugin::getInstance()->getAddresses()->getAddressById($id);

        if (!$address) {
            $address = new Address();
        }

        // Shared attributes
        $attributes = [
            'attention',
            'title',
            'firstName',
            'lastName',
            'address1',
            'address2',
            'city',
            'zipCode',
            'phone',
            'alternativePhone',
            'businessName',
            'businessTaxId',
            'businessId',
            'countryId',
            'stateValue',
            'phone'
        ];
        foreach ($attributes as $attr) {
            $address->$attr = Craft::$app->getRequest()->getParam($attr);
        }

        $address->isStoreLocation = true;

        if ($address->validate() && Plugin::getInstance()->getAddresses()->saveAddress($address)) {

            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Store Location saved.'));

            return $this->redirectToPostedUrl();
        }

        Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save Store Location.'));

        $variables = [
            'storeLocation' => $address
        ];

        // Send the model back to the template
        return $this->renderTemplate('commerce/settings/location/index', $variables);
    }

    /**
     * Save the records for lite settings.
     *
     * @param LiteSettings $liteSettings
     * @return bool
     */
    private function _saveLiteSettings(LiteSettings $liteSettings)
    {
        $taxRate = Plugin::getInstance()->getTaxRates()->getLiteTaxRate();
        $taxRate->rate = $liteSettings->taxRate;
        $taxRate->name = $liteSettings->taxName;
        $taxRate->include = $liteSettings->taxInclude;
        $taxSaved = Plugin::getInstance()->getTaxRates()->saveLiteTaxRate($taxRate, false);

        $shippingMethod = Plugin::getInstance()->getShippingMethods()->getLiteShippingMethod();
        $shippingMethodSaved = Plugin::getInstance()->getShippingMethods()->saveLiteShippingMethod($shippingMethod, false);

        $shippingRule = Plugin::getInstance()->getShippingRules()->getLiteShippingRule();
        $shippingRule->baseRate = $liteSettings->shippingBaseRate;
        $shippingRule->perItemRate = $liteSettings->shippingPerItemRate;
        $shippingRule->methodId = $shippingMethod->id;
        $shippingRuleSaved = Plugin::getInstance()->getShippingRules()->saveLiteShippingRule($shippingRule, false);

        return $taxSaved && $shippingMethodSaved && $shippingRuleSaved;
    }
}
