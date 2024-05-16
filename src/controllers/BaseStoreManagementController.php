<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;
use yii\web\Response as YiiResponse;

/**
 * Class BaseStoreSettingsController
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class BaseStoreManagementController extends BaseCpController
{
    public array $storeSettingsNav = [];

    /**
     * @return void
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\ForbiddenHttpException
     */
    public function init(): void
    {
        parent::init();

        $this->requirePermission('commerce-manageStoreSettings');
    }

    /**
     * @inheritDoc
     */
    public function renderTemplate(string $template, array $variables = [], ?string $templateMode = null): YiiResponse
    {
        $variables['storeSettingsNav'] = $this->getStoreSettingsNav();
        return parent::renderTemplate($template, $variables, $templateMode);
    }

    /**
     * @return array
     * @throws InvalidConfigException
     */
    protected function getStoreSettingsNav(): array
    {
        $userService = Craft::$app->getUser();

        $this->storeSettingsNav['general'] = [
            'label' => Craft::t('commerce', "General"),
            'path' => '',
            'disabled' => !$userService->checkPermission('commerce-manageGeneralStoreSettings'),
        ];

        $this->storeSettingsNav['payment-currencies'] = [
            'label' => Craft::t('commerce', 'Payment Currencies'),
            'path' => 'payment-currencies',
            'disabled' => !$userService->checkPermission('commerce-managePaymentCurrencies'),
        ];

        $managePromotions = $userService->checkPermission('commerce-managePromotions');
        $this->storeSettingsNav['pricing-heading'] = [
            'heading' => Craft::t('commerce', 'Pricing'),
        ];

        $this->storeSettingsNav['discounts'] = [
            'label' => Craft::t('commerce', 'Discounts'),
            'path' => 'discounts',
            'disabled' => !$managePromotions,
        ];

        if (Plugin::getInstance()->getCatalogPricingRules()->canUseCatalogPricingRules()) {
            $this->storeSettingsNav['pricing-rules'] = [
                'label' => Craft::t('commerce', 'Pricing Rules'),
                'path' => 'pricing-rules',
                'disabled' => !$managePromotions,
            ];
        } else {
            $this->storeSettingsNav['sales'] = [
                'label' => Craft::t('commerce', 'Sales'),
                'path' => 'sales',
                'disabled' => !$managePromotions,
            ];
        }


        $this->storeSettingsNav['shipping-header'] = [
            'heading' => Craft::t('commerce', 'Shipping'),
        ];

        $manageShipping = $userService->checkPermission('commerce-manageShipping');
        $this->storeSettingsNav['shippingmethods'] = [
            'label' => Craft::t('commerce', 'Shipping Methods'),
            'path' => 'shippingmethods',
            'disabled' => !$manageShipping,
        ];

        $this->storeSettingsNav['shippingzones'] = [
            'label' => Craft::t('commerce', 'Shipping Zones'),
            'path' => 'shippingzones',
            'disabled' => !$manageShipping,
        ];

        $this->storeSettingsNav['shippingcategories'] = [
            'label' => Craft::t('commerce', 'Shipping Categories'),
            'path' => 'shippingcategories',
            'disabled' => !$manageShipping,
        ];

        $this->storeSettingsNav['tax'] = [
            'heading' => Craft::t('commerce', 'Tax'),
        ];

        $manageTaxes = $userService->checkPermission('commerce-manageTaxes');
        if (Plugin::getInstance()->getTaxes()->viewTaxRates()) {
            $this->storeSettingsNav['taxrates'] = [
                'label' => Craft::t('commerce', 'Tax Rates'),
                'path' => 'taxrates',
                'disabled' => !$manageTaxes,
            ];
        }

        if (Plugin::getInstance()->getTaxes()->viewTaxZones()) {
            $this->storeSettingsNav['taxzones'] = [
                'label' => Craft::t('commerce', 'Tax Zones'),
                'path' => 'taxzones',
                'disabled' => !$manageTaxes,
            ];
        }

        if (Plugin::getInstance()->getTaxes()->viewTaxCategories()) {
            $this->storeSettingsNav['taxcategories'] = [
                'label' => Craft::t('commerce', 'Tax Categories'),
                'path' => 'taxcategories',
                'disabled' => !$manageTaxes,
            ];
        }

        return $this->storeSettingsNav;
    }
}
