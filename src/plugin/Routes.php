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

            // User edit screen
            $event->rules['myaccount/commerce'] = 'commerce/users/index';
            $event->rules['users/<userId:\d+>/commerce'] = 'commerce/users/index';

            // Products / Variants
            $event->rules['commerce/products'] = 'commerce/products/product-index';
            $event->rules['commerce/variants'] = 'commerce/variants/index';
            $event->rules['commerce/products/<productTypeHandle:{handle}>'] = 'commerce/products/product-index';
            $event->rules['commerce/variants/<productTypeHandle:{handle}>'] = 'commerce/variants/index';
            $event->rules['commerce/variants/<elementId:\d+><slug:(?:-[^\/]*)?>'] = 'elements/edit';
            $event->rules['commerce/products/<productType:{handle}>/new'] = 'commerce/products/create';
            $event->rules['commerce/products/<productTypeHandle:{handle}>/<elementId:\d+><slug:(?:-[^\/]*)?>'] = 'elements/edit';

            $event->rules['commerce/subscriptions'] = 'commerce/subscriptions/index';
            $event->rules['commerce/subscriptions/<plan:{handle}>'] = 'commerce/subscriptions/index';
            $event->rules['commerce/subscriptions/<subscriptionId:\d+>'] = 'commerce/subscriptions/edit';

            // Subscription plans
            $event->rules['commerce/subscription-plans'] = 'commerce/plans/plan-index';
            $event->rules['commerce/subscription-plans/<planId:\d+>'] = 'commerce/plans/edit-plan';
            $event->rules['commerce/subscription-plans/new'] = 'commerce/plans/edit-plan';

            // Product Types
            $event->rules['commerce/settings/producttypes'] = 'commerce/product-types/product-type-index';
            $event->rules['commerce/settings/producttypes/<productTypeId:\d+>'] = 'commerce/product-types/edit-product-type';
            $event->rules['commerce/settings/producttypes/new'] = 'commerce/product-types/edit-product-type';

            // Orders
            $event->rules['commerce/orders'] = 'commerce/orders/order-index';
            $event->rules['commerce/orders/<orderId:\d+>'] = 'commerce/orders/edit-order';

            $event->rules['commerce/orders/<storeHandle:{handle}>/create'] = 'commerce/orders/create';

            $event->rules['commerce/orders/<orderStatusHandle:{handle}>'] = 'commerce/orders/order-index';

            // Settings

            $event->rules['commerce/settings/stores'] = 'commerce/stores/stores-index';
            $event->rules['commerce/settings/stores/new'] = 'commerce/stores/edit-store';
            $event->rules['commerce/settings/stores/<storeId:\d+>'] = 'commerce/stores/edit-store';

            $event->rules['commerce/settings/sites'] = 'commerce/stores/edit-site-stores';

            $event->rules['commerce/settings/general'] = 'commerce/settings/edit';

            $event->rules['commerce/settings/ordersettings'] = 'commerce/order-settings/edit';

            $event->rules['commerce/settings/transfers'] = 'commerce/transfers/edit-settings';

            $event->rules['commerce/settings/gateways'] = 'commerce/gateways/index';
            $event->rules['commerce/settings/gateways/new'] = 'commerce/gateways/edit';
            $event->rules['commerce/settings/gateways/<id:\d+>'] = 'commerce/gateways/edit';

            $event->rules['commerce/settings/emails'] = 'commerce/emails/index';
            $event->rules['commerce/settings/emails/<storeHandle:{handle}>/new'] = 'commerce/emails/edit';
            $event->rules['commerce/settings/emails/<storeHandle:{handle}>/<id:\d+>'] = 'commerce/emails/edit';

            $event->rules['commerce/settings/pdfs'] = 'commerce/pdfs/index';
            $event->rules['commerce/settings/pdfs/<storeHandle:{handle}>/new'] = 'commerce/pdfs/edit';
            $event->rules['commerce/settings/pdfs/<storeHandle:{handle}>/<id:\d+>'] = 'commerce/pdfs/edit';

            $event->rules['commerce/settings/orderstatuses'] = 'commerce/order-statuses/index';
            $event->rules['commerce/settings/orderstatuses/<storeHandle:{handle}>/new'] = 'commerce/order-statuses/edit';
            $event->rules['commerce/settings/orderstatuses/<storeHandle:{handle}>/<id:\d+>'] = 'commerce/order-statuses/edit';

            $event->rules['commerce/settings/lineitemstatuses'] = 'commerce/line-item-statuses/index';
            $event->rules['commerce/settings/lineitemstatuses/<storeHandle:{handle}>/new'] = 'commerce/line-item-statuses/edit';
            $event->rules['commerce/settings/lineitemstatuses/<storeHandle:{handle}>/<id:\d+>'] = 'commerce/line-item-statuses/edit';

            // Store Settings
            $event->rules['commerce/store-management'] = 'commerce/store-management/index'; // Redirects to the first store
            $event->rules['commerce/store-management/<storeHandle:{handle}>'] = 'commerce/store-management/edit';

            $event->rules['commerce/store-management/<storeHandle:{handle}>/payment-currencies'] = 'commerce/payment-currencies/index';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/payment-currencies/new'] = 'commerce/payment-currencies/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/payment-currencies/<id:\d+>'] = 'commerce/payment-currencies/edit';

            // Shipping
            $event->rules['commerce/store-management/<storeHandle:{handle}>/shippingzones'] = 'commerce/shipping-zones/index';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/shippingzones/new'] = 'commerce/shipping-zones/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/shippingzones/<id:\d+>'] = 'commerce/shipping-zones/edit';

            $event->rules['commerce/store-management/<storeHandle:{handle}>/shippingcategories'] = 'commerce/shipping-categories/index';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/shippingcategories/new'] = 'commerce/shipping-categories/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/shippingcategories/<id:\d+>'] = 'commerce/shipping-categories/edit';

            $event->rules['commerce/store-management/<storeHandle:{handle}>/shippingmethods'] = 'commerce/shipping-methods/index';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/shippingmethods/new'] = 'commerce/shipping-methods/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/shippingmethods/<id:\d+>'] = 'commerce/shipping-methods/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/shippingmethods/<methodId:\d+>/shippingrules/new'] = 'commerce/shipping-rules/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/shippingmethods/<methodId:\d+>/shippingrules/<ruleId:\d+>'] = 'commerce/shipping-rules/edit';

            // Taxes
            $event->rules['commerce/store-management/<storeHandle:{handle}>/taxcategories'] = 'commerce/tax-categories/index';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/taxcategories/new'] = 'commerce/tax-categories/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/taxcategories/<id:\d+>'] = 'commerce/tax-categories/edit';

            $event->rules['commerce/store-management/<storeHandle:{handle}>/taxzones'] = 'commerce/tax-zones/index';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/taxzones/new'] = 'commerce/tax-zones/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/taxzones/<id:\d+>'] = 'commerce/tax-zones/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/taxrates'] = 'commerce/tax-rates/index';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/taxrates/new'] = 'commerce/tax-rates/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/taxrates/<id:\d+>'] = 'commerce/tax-rates/edit';

            // Sales
            $event->rules['commerce/store-management/<storeHandle:{handle}>/sales'] = 'commerce/sales/index';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/sales/new'] = 'commerce/sales/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/sales/<id:\d+>'] = 'commerce/sales/edit';

            // Discounts
            $event->rules['commerce/store-management/<storeHandle:{handle}>/discounts'] = 'commerce/discounts/index';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/discounts/new'] = 'commerce/discounts/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/discounts/<id:\d+>'] = 'commerce/discounts/edit';

            // Pricing
            $event->rules['commerce/store-management/<storeHandle:{handle}>/pricing-rules'] = 'commerce/catalog-pricing-rules/index';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/pricing-rules/new'] = 'commerce/catalog-pricing-rules/edit';
            $event->rules['commerce/store-management/<storeHandle:{handle}>/pricing-rules/<id:\d+>'] = 'commerce/catalog-pricing-rules/edit';

            // Inventory
            $event->rules['commerce/inventory'] = 'commerce/inventory/edit-location-levels'; // redirect to the first location
            $event->rules['commerce/inventory/levels'] = 'commerce/inventory/edit-location-levels'; // redirect to the first location

            $event->rules['commerce/inventory/item/<inventoryItemId:\d+>'] = 'commerce/inventory/item-edit';
            $event->rules['commerce/inventory/levels/<inventoryLocationHandle:{handle}>'] = 'commerce/inventory/edit-location-levels';

            $event->rules['commerce/inventory-locations'] = 'commerce/inventory-locations/index';
            $event->rules['commerce/inventory-locations/new'] = 'commerce/inventory-locations/edit';
            $event->rules['commerce/inventory-locations/<inventoryLocationId:\d+>'] = 'commerce/inventory-locations/edit';

            $event->rules['commerce/inventory/transfers'] = 'commerce/transfers/index';
            $event->rules['commerce/inventory/transfers/<elementId:\\d+>'] = 'elements/edit';

            // Donations
            $event->rules['commerce/donations'] = 'commerce/donations/edit';
        });
    }
}
