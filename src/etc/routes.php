<?php

return [

    // Product Routes
    'commerce/products' => ['action' => 'commerce/products/productIndex'],
    'commerce/products/(?P<productTypeHandle>{handle})' => ['action' => 'commerce/products/productIndex'],
    'commerce/products/(?P<productTypeHandle>{handle})/new/(?P<localeId>\w+)' => ['action' => 'commerce/products/editProduct'],
    'commerce/products/(?P<productTypeHandle>{handle})/new' => ['action' => 'commerce/products/editProduct'],
    'commerce/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)(?:-{slug})?/(?P<localeId>\w+)' => ['action' => 'commerce/products/editProduct'],
    'commerce/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)(?:-{slug})' => ['action' => 'commerce/products/editProduct'],
    'commerce/settings/producttypes' => ['action' => 'commerce/productTypes/index'],
    'commerce/settings/producttypes/(?P<productTypeId>\d+)' => ['action' => 'commerce/productTypes/editProductType'],
    'commerce/settings/producttypes/new' => ['action' => 'commerce/productTypes/editProductType'],

    // Order Routes
    'commerce/orders' => ['action' => 'commerce/orders/orderIndex'],
    'commerce/orders/new' => ['action' => 'commerce/orders/editOrder'],
    'commerce/orders/(?P<orderId>\d+)' => ['action' => 'commerce/orders/editOrder'],

    // Settings
    'commerce/settings' => ['action' => 'commerce/settings/index'],
    'commerce/settings/general' => ['action' => 'commerce/settings/edit'],
    'commerce/settings/taxcategories' => ['action' => 'commerce/taxCategories/index'],
    'commerce/settings/taxcategories/new' => ['action' => 'commerce/taxCategories/edit'],
    'commerce/settings/taxcategories/(?P<id>\d+)' => ['action' => 'commerce/taxCategories/edit'],
    'commerce/settings/countries' => ['action' => 'commerce/countries/index'],
    'commerce/settings/countries/new' => ['action' => 'commerce/countries/edit'],
    'commerce/settings/countries/(?P<id>\d+)' => ['action' => 'commerce/countries/edit'],
    'commerce/settings/states' => ['action' => 'commerce/states/index'],
    'commerce/settings/states/new' => ['action' => 'commerce/states/edit'],
    'commerce/settings/states/(?P<id>\d+)' => ['action' => 'commerce/states/edit'],
    'commerce/settings/taxzones' => ['action' => 'commerce/taxZones/index'],
    'commerce/settings/taxzones/new' => ['action' => 'commerce/taxZones/edit'],
    'commerce/settings/taxzones/(?P<id>\d+)' => ['action' => 'commerce/taxZones/edit'],
    'commerce/settings/taxrates' => ['action' => 'commerce/taxRates/index'],
    'commerce/settings/taxrates/new' => ['action' => 'commerce/taxRates/edit'],
    'commerce/settings/taxrates/(?P<id>\d+)' => ['action' => 'commerce/taxRates/edit'],

    'commerce/settings/ordersettings' => ['action' => 'commerce/orderSettings/edit'],
    'commerce/settings/paymentmethods' => ['action' => 'commerce/paymentMethods/index'],
    'commerce/settings/paymentmethods/new' => ['action' => 'commerce/paymentMethods/edit'],
    'commerce/settings/paymentmethods/(?P<id>\d+)' => ['action' => 'commerce/paymentMethods/edit'],

    'commerce/settings/shippingmethods' => ['action' => 'commerce/shippingMethods/index'],
    'commerce/settings/shippingmethods/new' => ['action' => 'commerce/shippingMethods/edit'],
    'commerce/settings/shippingmethods/(?P<id>\d+)' => ['action' => 'commerce/shippingMethods/edit'],
    'commerce/settings/shippingmethods/(?P<methodId>\d+)/shippingrules/new' => ['action' => 'commerce/shippingRules/edit'],
    'commerce/settings/shippingmethods/(?P<methodId>\d+)/shippingrules/(?P<ruleId>\d+)' => ['action' => 'commerce/shippingRules/edit'],
    'commerce/settings/emails' => ['action' => 'commerce/emails/index'],
    'commerce/settings/emails/new' => ['action' => 'commerce/emails/edit'],
    'commerce/settings/emails/(?P<id>\d+)' => ['action' => 'commerce/emails/edit'],

    'commerce/settings/orderstatuses' => ['action' => 'commerce/orderStatuses/index'],
    'commerce/settings/orderstatuses/new' => ['action' => 'commerce/orderStatuses/edit'],
    'commerce/settings/orderstatuses/(?P<id>\d+)' => ['action' => 'commerce/orderStatuses/edit'],


    // Promotions
    'commerce/promotions/sales' => ['action' => 'commerce/sales/index'],
    'commerce/promotions/sales/new' => ['action' => 'commerce/sales/edit'],
    'commerce/promotions/sales/(?P<id>\d+)' => ['action' => 'commerce/sales/edit'],
    'commerce/promotions/discounts' => ['action' => 'commerce/discounts/index'],
    'commerce/promotions/discounts/new' => ['action' => 'commerce/discounts/edit'],
    'commerce/promotions/discounts/(?P<id>\d+)' => ['action' => 'commerce/discounts/edit'],

    // Customers
    'commerce/customers' => ['action' => 'commerce/customers/index'],
    'commerce/customers/(?P<id>\d+)' => ['action' => 'commerce/customers/edit'],
    'commerce/addresses/(?P<id>\d+)' => ['action' => 'commerce/addresss/edit'],
];
