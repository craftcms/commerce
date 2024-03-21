<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\Plugin;
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
     */
    protected function getStoreSettingsNav(): array
    {
        $this->storeSettingsNav['general'] = [
            'label' => Craft::t('commerce', "General"),
            'path' => '',
        ];

        $this->storeSettingsNav['payment-currencies'] = [
            'label' => Craft::t('commerce', 'Payment Currencies'),
            'path' => 'payment-currencies',
        ];

        $this->storeSettingsNav['inventory-heading'] = [
            'heading' => Craft::t('commerce', 'Inventory'),
        ];

        $this->storeSettingsNav['inventory-locations'] = [
            'label' => Craft::t('commerce', "Locations"),
            'path' => 'inventory-locations',
        ];

        // TODO: Split into separate permissions
        if (Craft::$app->getUser()->checkPermission('commerce-managePromotions')) {
            $this->storeSettingsNav['pricing-heading'] = [
                'heading' => Craft::t('commerce', 'Pricing'),
            ];

            $this->storeSettingsNav['discounts'] = [
                'label' => Craft::t('commerce', 'Discounts'),
                'path' => 'discounts',
            ];

            if (Plugin::getInstance()->getCatalogPricingRules()->canUseCatalogPricingRules()) {
                $this->storeSettingsNav['pricing-rules'] = [
                    'label' => Craft::t('commerce', 'Pricing Rules'),
                    'path' => 'pricing-rules',
                ];
            } else {
                $this->storeSettingsNav['sales'] = [
                    'label' => Craft::t('commerce', 'Sales'),
                    'path' => 'sales',
                ];
            }
        }

        $this->storeSettingsNav['shipping-header'] = [
            'heading' => Craft::t('commerce', 'Shipping'),
        ];

        if (Craft::$app->getUser()->checkPermission('commerce-manageShipping')) {
            $this->storeSettingsNav['shippingmethods'] = [
                'label' => Craft::t('commerce', 'Shipping Methods'),
                'path' => 'shippingmethods',
            ];

            $this->storeSettingsNav['shippingzones'] = [
                'label' => Craft::t('commerce', 'Shipping Zones'),
                'path' => 'shippingzones',
            ];

            $this->storeSettingsNav['shippingcategories'] = [
                'label' => Craft::t('commerce', 'Shipping Categories'),
                'path' => 'shippingcategories',
            ];
        }

        $this->storeSettingsNav['tax'] = [
            'heading' => Craft::t('commerce', 'Tax'),
        ];

        if (Craft::$app->getUser()->checkPermission('commerce-manageTaxes')) {

            if (Plugin::getInstance()->getTaxes()->viewTaxRates()) {
                $this->storeSettingsNav['taxrates'] = [
                    'label' => Craft::t('commerce', 'Tax Rates'),
                    'path' => 'taxrates',
                ];
            }

            if (Plugin::getInstance()->getTaxes()->viewTaxZones()) {
                $this->storeSettingsNav['taxzones'] = [
                    'label' => Craft::t('commerce', 'Tax Zones'),
                    'path' => 'taxzones',
                ];
            }

            if (Plugin::getInstance()->getTaxes()->viewTaxCategories()) {
                $this->storeSettingsNav['taxcategories'] = [
                    'label' => Craft::t('commerce', 'Tax Categories'),
                    'path' => 'taxcategories',
                ];
            }
        }

        if (Craft::$app->getUser()->checkPermission('commerce-manageSubscriptions')) {
            $this->storeSettingsNav['subscriptions'] = [
                'heading' => Craft::t('commerce', 'Subscriptions'),
            ];

            $this->storeSettingsNav['subscription-plans'] = [
                'label' => Craft::t('commerce', 'Plans'),
                'path' => 'subscription-plans',
            ];
        }

        $this->storeSettingsNav['subscriptions'] = [
            'heading' => Craft::t('commerce', 'Subscriptions'),
        ];

        $this->storeSettingsNav['donation-heading'] = [
            'heading' => Craft::t('commerce', 'Donation'),
        ];

        $this->storeSettingsNav['donation'] = [
            'label' => Craft::t('commerce', 'Donation'),
            'path' => 'donation',
        ];


        return $this->storeSettingsNav;
    }
}
