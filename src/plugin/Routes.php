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
            $event->rules['commerce/variants'] = 'commerce/products/variant-index';
            $event->rules['commerce/products/<productTypeHandle:{handle}>'] = 'commerce/products/product-index';
            $event->rules['commerce/variants/<productTypeHandle:{handle}>'] = 'commerce/products/variant-index';
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

            $event->rules['commerce/addresses/new'] = 'commerce/addresses/edit';
            $event->rules['commerce/addresses/<addressId:\d+>'] = 'commerce/addresses/edit';

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
            $event->rules['commerce/store-settings/store'] = 'commerce/store/edit';

            $event->rules['commerce/store-settings/paymentcurrencies'] = 'commerce/payment-currencies/index';
            $event->rules['commerce/store-settings/paymentcurrencies/new'] = 'commerce/payment-currencies/edit';
            $event->rules['commerce/store-settings/paymentcurrencies/<id:\d+>'] = 'commerce/payment-currencies/edit';

            $event->rules['commerce/store-settings/donation'] = 'commerce/donations/edit';

            // Store Settings - Regions
            $event->rules['commerce/store-settings/countries'] = 'commerce/countries/index';
            $event->rules['commerce/store-settings/countries/new'] = 'commerce/countries/edit';
            $event->rules['commerce/store-settings/countries/<id:\d+>'] = 'commerce/countries/edit';

            $event->rules['commerce/store-settings/states'] = 'commerce/states/index';
            $event->rules['commerce/store-settings/states/new'] = 'commerce/states/edit';
            $event->rules['commerce/store-settings/states/<id:\d+>'] = 'commerce/states/edit';

            // Shipping
            $event->rules['commerce/shipping/shippingzones'] = 'commerce/shipping-zones/index';
            $event->rules['commerce/shipping/shippingzones/new'] = 'commerce/shipping-zones/edit';
            $event->rules['commerce/shipping/shippingzones/<id:\d+>'] = 'commerce/shipping-zones/edit';

            $event->rules['commerce/shipping/shippingcategories'] = 'commerce/shipping-categories/index';
            $event->rules['commerce/shipping/shippingcategories/new'] = 'commerce/shipping-categories/edit';
            $event->rules['commerce/shipping/shippingcategories/<id:\d+>'] = 'commerce/shipping-categories/edit';

            $event->rules['commerce/shipping/shippingmethods'] = 'commerce/shipping-methods/index';
            $event->rules['commerce/shipping/shippingmethods/new'] = 'commerce/shipping-methods/edit';
            $event->rules['commerce/shipping/shippingmethods/<id:\d+>'] = 'commerce/shipping-methods/edit';
            $event->rules['commerce/shipping/shippingmethods/<methodId:\d+>/shippingrules/new'] = 'commerce/shipping-rules/edit';
            $event->rules['commerce/shipping/shippingmethods/<methodId:\d+>/shippingrules/<ruleId:\d+>'] = 'commerce/shipping-rules/edit';

            // Subscription plans
            $event->rules['commerce/store-settings/subscription-plans'] = 'commerce/plans/plan-index';
            $event->rules['commerce/store-settings/subscription-plans/plan/<planId:\d+>'] = 'commerce/plans/edit-plan';
            $event->rules['commerce/store-settings/subscription-plans/plan/new'] = 'commerce/plans/edit-plan';

            // Taxes
            $event->rules['commerce/tax/taxcategories'] = 'commerce/tax-categories/index';
            $event->rules['commerce/tax/taxcategories/new'] = 'commerce/tax-categories/edit';
            $event->rules['commerce/tax/taxcategories/<id:\d+>'] = 'commerce/tax-categories/edit';

            $event->rules['commerce/tax/taxzones'] = 'commerce/tax-zones/index';
            $event->rules['commerce/tax/taxzones/new'] = 'commerce/tax-zones/edit';
            $event->rules['commerce/tax/taxzones/<id:\d+>'] = 'commerce/tax-zones/edit';
            $event->rules['commerce/tax/taxrates'] = 'commerce/tax-rates/index';
            $event->rules['commerce/tax/taxrates/new'] = 'commerce/tax-rates/edit';
            $event->rules['commerce/tax/taxrates/<id:\d+>'] = 'commerce/tax-rates/edit';

            // Promotions
            $event->rules['commerce/promotions/sales'] = 'commerce/sales/index';
            $event->rules['commerce/promotions/sales/new'] = 'commerce/sales/edit';
            $event->rules['commerce/promotions/sales/<id:\d+>'] = 'commerce/sales/edit';

            $event->rules['commerce/promotions/discounts'] = 'commerce/discounts/index';
            $event->rules['commerce/promotions/discounts/new'] = 'commerce/discounts/edit';
            $event->rules['commerce/promotions/discounts/<id:\d+>'] = 'commerce/discounts/edit';
        });
    }
}
