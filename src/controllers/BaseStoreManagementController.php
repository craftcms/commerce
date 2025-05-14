<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\web\UrlManager;
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

        if (!isset($variables['storeHandle'])) {
            /** @var UrlManager $urlManager */
            $urlManager = Craft::$app->getUrlManager();
            $routeParams = $urlManager->getRouteParams();

            // Make sure store handle is always passed to the template
            if (isset($routeParams['storeHandle'])) {
                $variables['storeHandle'] = $routeParams['storeHandle'];
            }
        }

        if (!isset($variables['storeSwitcher'])) {
            $variables['storeSwitcher'] = $this->getStoreSwitcher($variables['storeHandle']);
        }

        return parent::renderTemplate($template, $variables, $templateMode);
    }

    /**
     * @param string|null $storeHandle
     * @return array
     * @throws InvalidConfigException
     * @since 5.3.0
     */
    protected function getStoreSwitcher(?string $storeHandle = null): array
    {
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        $store = $storeHandle ? Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle) : null;

        $storeItems = $stores->filter(function(Store $s) {
            // Check that the user has permission to access a site that this store is related to
            foreach ($s->getSites() as $site) {
                if (Craft::$app->getUser()->checkPermission('editSite:' . $site->uid)) {
                    return true;
                }
            }

            return false;
        })->map(function(Store $s) use ($storeHandle) {
            $segments = Craft::$app->getRequest()->getSegments();
            $storeSubSection = count($segments) >= 4 ? $segments[3] : null;

            return [
                'status' => null,
                'label' => Craft::t('site', $s->getName()),
                'url' => 'commerce/store-management/' . $s->handle . ($storeSubSection ? '/' . $storeSubSection : ''),
                'selected' => $storeHandle === $s->handle,
                'attributes' => [
                    'data' => [
                        'store-handle' => $s->handle,
                    ],
                ],
            ];
        })->all();

        return [
            'id' => 'site-crumb',
            'iconAltText' => Craft::t('commerce', 'Store'),
            'icon' => 'store',
            'label' => $store?->getName() ?? Craft::t('commerce', 'Store Management'),
            'menu' => [
                'label' => Craft::t('app', 'Select site'),
                'items' => $storeItems,
            ],
        ];
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
