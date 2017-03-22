<?php

return [

    // Products
    'commerce/products' => ['action' => 'commerce/products/productIndex'],
    'commerce/products/(?P<productTypeHandle>{handle})' => ['action' => 'commerce/products/productIndex'],
    'commerce/products/(?P<productTypeHandle>{handle})/new/(?P<localeId>\w+)' => ['action' => 'commerce/products/editProduct'],
    'commerce/products/(?P<productTypeHandle>{handle})/new' => ['action' => 'commerce/products/editProduct'],
    'commerce/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)(?:-{slug})?/(?P<localeId>\w+)' => ['action' => 'commerce/products/editProduct'],
    'commerce/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)(?:-{slug})?' => ['action' => 'commerce/products/editProduct'],
    'commerce/settings/producttypes' => ['action' => 'commerce/productTypes/index'],
    'commerce/settings/producttypes/(?P<productTypeId>\d+)' => ['action' => 'commerce/productTypes/editProductType'],
    'commerce/settings/producttypes/new' => ['action' => 'commerce/productTypes/editProductType'],

    // Orders
    'commerce/orders' => ['action' => 'commerce/orders/orderIndex'],
    'commerce/orders/(?P<orderId>\d+)' => ['action' => 'commerce/orders/editOrder'],

    // Addresses
    'commerce/addresses/(?P<addressId>\d+)' => ['action' => 'commerce/addresses/edit'],

    // Settings
    'commerce/settings' => ['action' => 'commerce/settings/index'],
    'commerce/settings/registration' => ['action' => 'commerce/registration/edit'],
    'commerce/settings/general' => ['action' => 'commerce/settings/edit'],

    // Taxes
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

    // Order Field Layout
    'commerce/settings/ordersettings' => ['action' => 'commerce/orderSettings/edit'],

    // Payment Methods
    'commerce/settings/paymentmethods' => ['action' => 'commerce/paymentMethods/index'],
    'commerce/settings/paymentmethods/new' => ['action' => 'commerce/paymentMethods/edit'],
    'commerce/settings/paymentmethods/(?P<id>\d+)' => ['action' => 'commerce/paymentMethods/edit'],

    // Payment Currencies
    'commerce/settings/paymentcurrencies' => ['action' => 'commerce/paymentCurrencies/index'],
    'commerce/settings/paymentcurrencies/new' => ['action' => 'commerce/paymentCurrencies/edit'],
    'commerce/settings/paymentcurrencies/(?P<id>\d+)' => ['action' => 'commerce/paymentCurrencies/edit'],

    // Shipping Zones
    'commerce/settings/shippingzones' => ['action' => 'commerce/shippingZones/index'],
    'commerce/settings/shippingzones/new' => ['action' => 'commerce/shippingZones/edit'],
    'commerce/settings/shippingzones/(?P<id>\d+)' => ['action' => 'commerce/shippingZones/edit'],

    // Shipping Categories
    'commerce/settings/shippingcategories' => ['action' => 'commerce/shippingCategories/index'],
    'commerce/settings/shippingcategories/new' => ['action' => 'commerce/shippingCategories/edit'],
    'commerce/settings/shippingcategories/(?P<id>\d+)' => ['action' => 'commerce/shippingCategories/edit'],

    // Shipping Methods
    'commerce/settings/shippingmethods' => ['action' => 'commerce/shippingMethods/index'],
    'commerce/settings/shippingmethods/new' => ['action' => 'commerce/shippingMethods/edit'],
    'commerce/settings/shippingmethods/(?P<id>\d+)' => ['action' => 'commerce/shippingMethods/edit'],
    'commerce/settings/shippingmethods/(?P<methodId>\d+)/shippingrules/new' => ['action' => 'commerce/shippingRules/edit'],
    'commerce/settings/shippingmethods/(?P<methodId>\d+)/shippingrules/(?P<ruleId>\d+)' => ['action' => 'commerce/shippingRules/edit'],

    // Emails
    'commerce/settings/emails' => ['action' => 'commerce/emails/index'],
    'commerce/settings/emails/new' => ['action' => 'commerce/emails/edit'],
    'commerce/settings/emails/(?P<id>\d+)' => ['action' => 'commerce/emails/edit'],

    // Order Statuses
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
    'commerce/customers/(?P<id>\d+)' => ['action' => 'commerce/customers/edit']
];
