<?php
namespace craft\commerce\plugin;

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;

trait Routes
{
    private function _registerCpRoutes()
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {

            $event->rules['commerce'] = ['template' => 'commerce/index'];

            $event->rules['commerce/products'] = 'commerce/products/product-index';
            $event->rules['commerce/products/(?P<productTypeHandle>{handle})'] = 'commerce/products/product-index';
            $event->rules['commerce/products/(?P<productTypeHandle>{handle})/new/(?P<localeId>\w+)'] = 'commerce/products/edit-product';
            $event->rules['commerce/products/(?P<productTypeHandle>{handle})/new'] = 'commerce/products/edit-product';
            $event->rules['commerce/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)(?:-{slug})?/(?P<localeId>\w+)'] = 'commerce/products/edit-product';
            $event->rules['commerce/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)(?:-{slug})?'] = 'commerce/products/edit-product';

            $event->rules['commerce/settings/producttypes'] = 'commerce/product-types/product-type-index';
            $event->rules['commerce/settings/producttypes/(?P<productTypeId>\d+)'] = 'commerce/product-types/edit-product-type';
            $event->rules['commerce/settings/producttypes/new'] = 'commerce/product-types/edit-product-type';

            $event->rules['commerce/orders'] = 'commerce/orders/order-index';
            $event->rules['commerce/orders/(?P<orderId>\d+)'] = 'commerce/orders/edit-order';

            $event->rules['commerce/addresses/(?P<addressId>\d+)'] = 'commerce/addresses/edit';

            $event->rules['commerce/settings'] = 'commerce/settings/index';
            $event->rules['commerce/settings/registration'] = 'commerce/registration/edit';
            $event->rules['commerce/settings/general'] = 'commerce/settings/edit';

            $event->rules['commerce/settings/taxcategories'] = 'commerce/tax-categories/index';
            $event->rules['commerce/settings/taxcategories/new'] = 'commerce/tax-categories/edit';
            $event->rules['commerce/settings/taxcategories/(?P<id>\d+)'] = 'commerce/tax-categories/edit';

            $event->rules['commerce/settings/countries'] = 'commerce/countries/index';
            $event->rules['commerce/settings/countries/new'] = 'commerce/countries/edit';
            $event->rules['commerce/settings/countries/(?P<id>\d+)'] = 'commerce/countries/edit';

            $event->rules['commerce/settings/states'] = 'commerce/states/index';
            $event->rules['commerce/settings/states/new'] = 'commerce/states/edit';
            $event->rules['commerce/settings/states/(?P<id>\d+)'] = 'commerce/states/edit';

            $event->rules['commerce/settings/taxzones'] = 'commerce/tax-zones/index';
            $event->rules['commerce/settings/taxzones/new'] = 'commerce/tax-zones/edit';
            $event->rules['commerce/settings/taxzones/(?P<id>\d+)'] = 'commerce/tax-zones/edit';
            $event->rules['commerce/settings/taxrates'] = 'commerce/tax-rates/index';
            $event->rules['commerce/settings/taxrates/new'] = 'commerce/tax-rates/edit';
            $event->rules['commerce/settings/taxrates/(?P<id>\d+)'] = 'commerce/tax-rates/edit';

            $event->rules['commerce/settings/ordersettings'] = 'commerce/order-settings/edit';

            $event->rules['commerce/settings/paymentmethods'] = 'commerce/payment-methods/index';
            $event->rules['commerce/settings/paymentmethods/new'] = 'commerce/payment-methods/edit';
            $event->rules['commerce/settings/paymentmethods/(?P<id>\d+)'] = 'commerce/payment-methods/edit';

            $event->rules['commerce/settings/paymentcurrencies'] = 'commerce/payment-currencies/index';
            $event->rules['commerce/settings/paymentcurrencies/new'] = 'commerce/payment-currencies/edit';
            $event->rules['commerce/settings/paymentcurrencies/(?P<id>\d+)'] = 'commerce/payment-currencies/edit';

            $event->rules['commerce/settings/shippingzones'] = 'commerce/shipping-zones/index';
            $event->rules['commerce/settings/shippingzones/new'] = 'commerce/shipping-zones/edit';
            $event->rules['commerce/settings/shippingzones/(?P<id>\d+)'] = 'commerce/shipping-zones/edit';

            $event->rules['commerce/settings/shippingcategories'] = 'commerce/shipping-categories/index';
            $event->rules['commerce/settings/shippingcategories/new'] = 'commerce/shipping-categories/edit';
            $event->rules['commerce/settings/shippingcategories/(?P<id>\d+)'] = 'commerce/shipping-categories/edit';

            $event->rules['commerce/settings/shippingmethods'] = 'commerce/shipping-methods/index';
            $event->rules['commerce/settings/shippingmethods/new'] = 'commerce/shipping-methods/edit';
            $event->rules['commerce/settings/shippingmethods/(?P<id>\d+)'] = 'commerce/shipping-methods/edit';
            $event->rules['commerce/settings/shippingmethods/(?P<methodId>\d+)/shippingrules/new'] = 'commerce/shipping-rules/edit';
            $event->rules['commerce/settings/shippingmethods/(?P<methodId>\d+)/shippingrules/(?P<ruleId>\d+)'] = 'commerce/shipping-rules/edit';

            $event->rules['commerce/settings/emails'] = 'commerce/emails/index';
            $event->rules['commerce/settings/emails/new'] = 'commerce/emails/edit';
            $event->rules['commerce/settings/emails/(?P<id>\d+)'] = 'commerce/emails/edit';

            $event->rules['commerce/settings/orderstatuses'] = 'commerce/order-statuses/index';
            $event->rules['commerce/settings/orderstatuses/new'] = 'commerce/order-statuses/edit';
            $event->rules['commerce/settings/orderstatuses/(?P<id>\d+)'] = 'commerce/order-statuses/edit';

            $event->rules['commerce/promotions/sales'] = 'commerce/sales/index';
            $event->rules['commerce/promotions/sales/new'] = 'commerce/sales/edit';
            $event->rules['commerce/promotions/sales/(?P<id>\d+)'] = 'commerce/sales/edit';
            $event->rules['commerce/promotions/discounts'] = 'commerce/discounts/index';
            $event->rules['commerce/promotions/discounts/new'] = 'commerce/discounts/edit';
            $event->rules['commerce/promotions/discounts/(?P<id>\d+)'] = 'commerce/discounts/edit';

            $event->rules['commerce/customers'] = 'commerce/customers/index';
            $event->rules['commerce/customers/(?P<id>\d+)'] = 'commerce/customers/edit';
        });
    }
}

