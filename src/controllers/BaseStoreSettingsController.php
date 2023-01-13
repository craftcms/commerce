<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;

/**
 * Class BaseStoreSettingsController
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class BaseStoreSettingsController extends BaseCpController
{
    public array $storeSettingsNav = [];

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
    }

    /**
     * @return array
     */
    protected function getStoreSettingsNav()
    {
        $this->storeSettingsNav['general'] = [
            'label' => Craft::t('commerce', "General"),
            'path' => '',
        ];
        $this->storeSettingsNav['payment-currencies'] = [
            'label' => Craft::t('commerce', 'Payment Currencies'),
            'path' => 'payment-currencies',
        ];
        $this->storeSettingsNav['donation'] = [
            'label' => Craft::t('commerce', 'Donations'),
            'path' => 'donation',
        ];
        $this->storeSettingsNav['subscriptions'] = [
            'heading' => Craft::t('commerce', 'Subscriptions'),
        ];

        $this->storeSettingsNav['subscription-plans'] = [
            'label' => Craft::t('commerce', 'Plans'),
            'path' => 'subscription-plans',
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

            $this->storeSettingsNav['pricing'] = [
                'label' => Craft::t('commerce', 'Pricing Rules'),
                'path' => 'price-rules',
            ];
        }

        $this->storeSettingsNav['shipping-header'] = [
            'heading' => Craft::t('commerce', 'Shipping'),
        ];

        if (Craft::$app->getUser()->checkPermission('commerce-manageShipping')) {
            $this->storeSettingsNav['shipping'] = [
                'label' => Craft::t('commerce', 'Shipping'),
                'path' => 'shipping',
            ];
        }

        $this->storeSettingsNav['tax'] = [
            'heading' => Craft::t('commerce', 'Tax'),
        ];

        if (Craft::$app->getUser()->checkPermission('commerce-manageTaxes')) {
            $this->storeSettingsNav['taxes'] = [
                'label' => Craft::t('commerce', 'Tax'),
                'path' => 'tax',
            ];
        }

        return $this->storeSettingsNav;
    }
}
