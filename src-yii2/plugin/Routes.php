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
     * @deprecated the webhook route is now registered in routes/web.php and routes/actions.php.
     */
    private function _registerSiteRoutes(): void
    {
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

            $event->rules['commerce/settings/transfers'] = 'commerce/settings/edit-transfer-settings';

            $event->rules['commerce/settings/subscriptions'] = 'commerce/settings/edit-subscription-settings';

            // commerce/settings/gateways* is now registered in routes/cp.php

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

            // Shipping is now registered in routes/cp.php

            // Taxes are now registered in routes/cp.php

            // Sales, Discounts, and Pricing Rules are now registered in routes/cp.php

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

            // commerce/donations is now registered in routes/cp.php
        });
    }
}
