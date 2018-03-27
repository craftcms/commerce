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
    // Private Methods
    // =========================================================================

    private function _registerCpRoutes()
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

            $event->rules['commerce/settings/subscriptions/plans'] = 'commerce/plans/plan-index';
            $event->rules['commerce/settings/subscriptions/plan/<planId:\d+>'] = 'commerce/plans/edit-plan';
            $event->rules['commerce/settings/subscriptions/plan/new'] = 'commerce/plans/edit-plan';

            $event->rules['commerce/orders'] = 'commerce/orders/order-index';
            $event->rules['commerce/orders/<orderId:\d+>'] = 'commerce/orders/edit-order';

            $event->rules['commerce/addresses/<addressId:\d+>'] = 'commerce/addresses/edit';

            $event->rules['commerce/settings/general'] = 'commerce/settings/edit';

            $event->rules['commerce/settings/location'] = 'commerce/settings/edit-location';

            $event->rules['commerce/settings/taxcategories'] = 'commerce/tax-categories/index';
            $event->rules['commerce/settings/taxcategories/new'] = 'commerce/tax-categories/edit';
            $event->rules['commerce/settings/taxcategories/<id:\d+>'] = 'commerce/tax-categories/edit';

            $event->rules['commerce/settings/countries'] = 'commerce/countries/index';
            $event->rules['commerce/settings/countries/new'] = 'commerce/countries/edit';
            $event->rules['commerce/settings/countries/<id:\d+>'] = 'commerce/countries/edit';

            $event->rules['commerce/settings/states'] = 'commerce/states/index';
            $event->rules['commerce/settings/states/new'] = 'commerce/states/edit';
            $event->rules['commerce/settings/states/<id:\d+>'] = 'commerce/states/edit';

            $event->rules['commerce/settings/taxzones'] = 'commerce/tax-zones/index';
            $event->rules['commerce/settings/taxzones/new'] = 'commerce/tax-zones/edit';
            $event->rules['commerce/settings/taxzones/<id:\d+>'] = 'commerce/tax-zones/edit';
            $event->rules['commerce/settings/taxrates'] = 'commerce/tax-rates/index';
            $event->rules['commerce/settings/taxrates/new'] = 'commerce/tax-rates/edit';
            $event->rules['commerce/settings/taxrates/<id:\d+>'] = 'commerce/tax-rates/edit';

            $event->rules['commerce/settings/ordersettings'] = 'commerce/order-settings/edit';

            $event->rules['commerce/settings/gateways'] = 'commerce/gateways/index';
            $event->rules['commerce/settings/gateways/new'] = 'commerce/gateways/edit';
            $event->rules['commerce/settings/gateways/<id:\d+>'] = 'commerce/gateways/edit';

            $event->rules['commerce/settings/paymentcurrencies'] = 'commerce/payment-currencies/index';
            $event->rules['commerce/settings/paymentcurrencies/new'] = 'commerce/payment-currencies/edit';
            $event->rules['commerce/settings/paymentcurrencies/<id:\d+>'] = 'commerce/payment-currencies/edit';

            $event->rules['commerce/settings/shippingzones'] = 'commerce/shipping-zones/index';
            $event->rules['commerce/settings/shippingzones/new'] = 'commerce/shipping-zones/edit';
            $event->rules['commerce/settings/shippingzones/<id:\d+>'] = 'commerce/shipping-zones/edit';

            $event->rules['commerce/settings/shippingcategories'] = 'commerce/shipping-categories/index';
            $event->rules['commerce/settings/shippingcategories/new'] = 'commerce/shipping-categories/edit';
            $event->rules['commerce/settings/shippingcategories/<id:\d+>'] = 'commerce/shipping-categories/edit';

            $event->rules['commerce/settings/shippingmethods'] = 'commerce/shipping-methods/index';
            $event->rules['commerce/settings/shippingmethods/new'] = 'commerce/shipping-methods/edit';
            $event->rules['commerce/settings/shippingmethods/<id:\d+>'] = 'commerce/shipping-methods/edit';
            $event->rules['commerce/settings/shippingmethods/<methodId:\d+>/shippingrules/new'] = 'commerce/shipping-rules/edit';
            $event->rules['commerce/settings/shippingmethods/<methodId:\d+>/shippingrules/<ruleId:\d+>'] = 'commerce/shipping-rules/edit';

            $event->rules['commerce/settings/emails'] = 'commerce/emails/index';
            $event->rules['commerce/settings/emails/new'] = 'commerce/emails/edit';
            $event->rules['commerce/settings/emails/<id:\d+>'] = 'commerce/emails/edit';

            $event->rules['commerce/settings/orderstatuses'] = 'commerce/order-statuses/index';
            $event->rules['commerce/settings/orderstatuses/new'] = 'commerce/order-statuses/edit';
            $event->rules['commerce/settings/orderstatuses/<id:\d+>'] = 'commerce/order-statuses/edit';

            $event->rules['commerce/promotions/sales'] = 'commerce/sales/index';
            $event->rules['commerce/promotions/sales/new'] = 'commerce/sales/edit';
            $event->rules['commerce/promotions/sales/<id:\d+>'] = 'commerce/sales/edit';

            $event->rules['commerce/promotions/discounts'] = 'commerce/discounts/index';
            $event->rules['commerce/promotions/discounts/new'] = 'commerce/discounts/edit';
            $event->rules['commerce/promotions/discounts/<id:\d+>'] = 'commerce/discounts/edit';

            $event->rules['commerce/customers'] = 'commerce/customers/index';
            $event->rules['commerce/customers/<id:\d+>'] = 'commerce/customers/edit';
        });
    }
}

