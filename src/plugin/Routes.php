<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\plugin;

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * Trait Routes
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
trait Routes
{
    /**
     * @since 3.1.10
     */
    private function _registerSiteRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['commerce/webhooks/process-webhook/gateway/<gatewayId:\d+>'] = 'commerce/webhooks/process-webhook';
        });
    }

    /**
     * @since 2.0
     */
    private function _registerCpRoutes(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['commerce'] = ['template' => 'commerce/index'];

            $event->rules['commerce/products'] = 'commerce/products/product-index';
            $event->rules['commerce/variants'] = 'commerce/variants/index';
            $event->rules['commerce/products/<productTypeHandle:{handle}>'] = 'commerce/products/product-index';
            $event->rules['commerce/variants/<productTypeHandle:{handle}>'] = 'commerce/variants/index';
            $event->rules['commerce/variants/<elementId:\d+><slug:(?:-[^\/]*)?>'] = 'elements/edit';
            $event->rules['commerce/products/<productTypeHandle:{handle}>/new'] = 'commerce/products/edit-product';
            $event->rules['commerce/products/<productTypeHandle:{handle}>/new/<siteHandle:{handle}>'] = 'commerce/products/edit-product';
            $event->rules['commerce/products/<productTypeHandle:{handle}>/<productId:\d+><slug:(?:-[^\/]*)?>'] = 'commerce/products/edit-product';
            $event->rules['commerce/products/<productTypeHandle:{handle}>/<productId:\d+><slug:(?:-[^\/]*)?>/<siteHandle:{handle}>'] = 'commerce/products/edit-product';

            $event->rules['commerce/subscriptions'] = 'commerce/subscriptions/index';
            $event->rules['commerce/subscriptions/<plan:{handle}>'] = 'commerce/subscriptions/index';
            $event->rules['commerce/subscriptions/<subscriptionId:\d+>'] = 'commerce/subscriptions/edit';

            $event->rules['commerce/settings/producttypes'] = 'commerce/product-types/product-type-index';
            $event->rules['commerce/settings/producttypes/<productTypeId:\d+>'] = 'commerce/product-types/edit-product-type';
            $event->rules['commerce/settings/producttypes/new'] = 'commerce/product-types/edit-product-type';

            $event->rules['commerce/orders'] = 'commerce/orders/order-index';
            $event->rules['commerce/orders/<orderId:\d+>'] = 'commerce/orders/edit-order';

            $event->rules['commerce/orders/create'] = 'commerce/orders/create';

            $event->rules['commerce/orders/<orderStatusHandle:{handle}>'] = 'commerce/orders/order-index';

            // Settings

            $event->rules['commerce/settings/stores'] = 'commerce/stores/stores-index';
            $event->rules['commerce/settings/stores/new'] = 'commerce/stores/edit-store';
            $event->rules['commerce/settings/stores/<storeId:\d+>'] = 'commerce/stores/edit-store';

            $event->rules['commerce/settings/sites'] = 'commerce/stores/edit-site-stores';

            $event->rules['commerce/settings/general'] = 'commerce/settings/edit';

            $event->rules['commerce/settings/ordersettings'] = 'commerce/order-settings/edit';

            $event->rules['commerce/settings/gateways'] = 'commerce/gateways/index';
            $event->rules['commerce/settings/gateways/new'] = 'commerce/gateways/edit';
            $event->rules['commerce/settings/gateways/<id:\d+>'] = 'commerce/gateways/edit';

            $event->rules['commerce/settings/emails'] = 'commerce/emails/index';
            $event->rules['commerce/settings/emails/new'] = 'commerce/emails/edit';
            $event->rules['commerce/settings/emails/<id:\d+>'] = 'commerce/emails/edit';

            $event->rules['commerce/settings/pdfs'] = 'commerce/pdfs/index';
            $event->rules['commerce/settings/pdfs/new'] = 'commerce/pdfs/edit';
            $event->rules['commerce/settings/pdfs/<id:\d+>'] = 'commerce/pdfs/edit';

            $event->rules['commerce/settings/orderstatuses'] = 'commerce/order-statuses/index';
            $event->rules['commerce/settings/orderstatuses/new'] = 'commerce/order-statuses/edit';
            $event->rules['commerce/settings/orderstatuses/<id:\d+>'] = 'commerce/order-statuses/edit';

            $event->rules['commerce/settings/lineitemstatuses'] = 'commerce/line-item-statuses/index';
            $event->rules['commerce/settings/lineitemstatuses/new'] = 'commerce/line-item-statuses/edit';
            $event->rules['commerce/settings/lineitemstatuses/<id:\d+>'] = 'commerce/line-item-statuses/edit';

            // Store Settings
            $event->rules['commerce/store-settings'] = 'commerce/store-settings/edit'; // Redirects to the first store
            $event->rules['commerce/store-settings/<storeHandle:{handle}>'] = 'commerce/store-settings/edit';

            $event->rules['commerce/store-settings/<storeHandle:{handle}>/payment-currencies'] = 'commerce/payment-currencies/index';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/payment-currencies/new'] = 'commerce/payment-currencies/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/payment-currencies/<id:\d+>'] = 'commerce/payment-currencies/edit';

            $event->rules['commerce/store-settings/<storeHandle:{handle}>/donation'] = 'commerce/donations/edit';

            // Shipping
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/shipping/shippingzones'] = 'commerce/shipping-zones/index';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/shipping/shippingzones/new'] = 'commerce/shipping-zones/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/shipping/shippingzones/<id:\d+>'] = 'commerce/shipping-zones/edit';

            $event->rules['commerce/store-settings/<storeHandle:{handle}>/shipping/shippingcategories'] = 'commerce/shipping-categories/index';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/shipping/shippingcategories/new'] = 'commerce/shipping-categories/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/shipping/shippingcategories/<id:\d+>'] = 'commerce/shipping-categories/edit';

            $event->rules['commerce/store-settings/<storeHandle:{handle}>/shipping/shippingmethods'] = 'commerce/shipping-methods/index';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/shipping/shippingmethods/new'] = 'commerce/shipping-methods/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/shipping/shippingmethods/<id:\d+>'] = 'commerce/shipping-methods/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/shipping/shippingmethods/<methodId:\d+>/shippingrules/new'] = 'commerce/shipping-rules/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/shipping/shippingmethods/<methodId:\d+>/shippingrules/<ruleId:\d+>'] = 'commerce/shipping-rules/edit';

            // Subscription plans
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/store-settings/subscription-plans'] = 'commerce/plans/plan-index';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/store-settings/subscription-plans/plan/<planId:\d+>'] = 'commerce/plans/edit-plan';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/store-settings/subscription-plans/plan/new'] = 'commerce/plans/edit-plan';

            // Taxes
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/tax/taxcategories'] = 'commerce/tax-categories/index';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/tax/taxcategories/new'] = 'commerce/tax-categories/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/tax/taxcategories/<id:\d+>'] = 'commerce/tax-categories/edit';

            $event->rules['commerce/store-settings/<storeHandle:{handle}>/tax/taxzones'] = 'commerce/tax-zones/index';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/tax/taxzones/new'] = 'commerce/tax-zones/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/tax/taxzones/<id:\d+>'] = 'commerce/tax-zones/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/tax/taxrates'] = 'commerce/tax-rates/index';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/tax/taxrates/new'] = 'commerce/tax-rates/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/tax/taxrates/<id:\d+>'] = 'commerce/tax-rates/edit';

            // @TODO Sales going away
           $event->rules['commerce/promotions/sales'] = 'commerce/sales/index';
           $event->rules['commerce/promotions/sales/new'] = 'commerce/sales/edit';
           $event->rules['commerce/promotions/sales/<id:\d+>'] = 'commerce/sales/edit';

            $event->rules['commerce/store-settings/<storeHandle:{handle}>/discounts'] = 'commerce/discounts/index';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/discounts/new'] = 'commerce/discounts/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/discounts/<id:\d+>'] = 'commerce/discounts/edit';

            $event->rules['commerce/store-settings/<storeHandle:{handle}>/pricing-rules'] = 'commerce/catalog-pricing-rules/index';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/pricing-rules/new'] = 'commerce/catalog-pricing-rules/edit';
            $event->rules['commerce/store-settings/<storeHandle:{handle}>/pricing-rules/<id:\d+>'] = 'commerce/catalog-pricing-rules/edit';
        });
    }
}
