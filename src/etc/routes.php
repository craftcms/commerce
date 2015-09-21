<?php

return [

	// Product Routes
	'commerce/products' => ['action' => 'commerce/product/productIndex'],
	'commerce/products/(?P<productTypeHandle>{handle})/new/(?P<localeId>\w+)' => ['action' => 'commerce/product/editProduct'],
	'commerce/products/(?P<productTypeHandle>{handle})/new' => ['action' => 'commerce/product/editProduct'],
	'commerce/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)(?:-{slug})?/(?P<localeId>\w+)' => ['action' => 'commerce/product/editProduct'],
	'commerce/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)(?:-{slug})' => ['action' => 'commerce/product/editProduct'],
	'commerce/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)/variants/new' => ['action' => 'commerce/variant/edit'],
	'commerce/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)/variants/(?P<id>\d+)' => ['action' => 'commerce/variant/edit'],
	'commerce/settings/producttypes' => ['action' => 'commerce/productType/index'],
	'commerce/settings/producttypes/(?P<productTypeId>\d+)' => ['action' => 'commerce/productType/editProductType'],
	'commerce/settings/producttypes/new' => ['action' => 'commerce/productType/editProductType'],

	// Order Routes
	'commerce/orders' => ['action' => 'commerce/order/orderIndex'],
	'commerce/orders/new' => ['action' => 'commerce/order/editOrder'],
	'commerce/orders/(?P<orderId>\d+)' => ['action' => 'commerce/order/editOrder'],

	// Settings
	'commerce/settings' => ['action' => 'commerce/settings/index'],
	'commerce/settings/global' => ['action' => 'commerce/settings/edit'],
	'commerce/settings/taxcategories' => ['action' => 'commerce/taxCategory/index'],
	'commerce/settings/taxcategories/new' => ['action' => 'commerce/taxCategory/edit'],
	'commerce/settings/taxcategories/(?P<id>\d+)' => ['action' => 'commerce/taxCategory/edit'],
	'commerce/settings/countries' => ['action' => 'commerce/country/index'],
	'commerce/settings/countries/new' => ['action' => 'commerce/country/edit'],
	'commerce/settings/countries/(?P<id>\d+)' => ['action' => 'commerce/country/edit'],
	'commerce/settings/states' => ['action' => 'commerce/state/index'],
	'commerce/settings/states/new' => ['action' => 'commerce/state/edit'],
	'commerce/settings/states/(?P<id>\d+)' => ['action' => 'commerce/state/edit'],
	'commerce/settings/taxzones' => ['action' => 'commerce/taxZone/index'],
	'commerce/settings/taxzones/new' => ['action' => 'commerce/taxZone/edit'],
	'commerce/settings/taxzones/(?P<id>\d+)' => ['action' => 'commerce/taxZone/edit'],
	'commerce/settings/taxrates' => ['action' => 'commerce/taxRate/index'],
	'commerce/settings/taxrates/new' => ['action' => 'commerce/taxRate/edit'],
	'commerce/settings/taxrates/(?P<id>\d+)' => ['action' => 'commerce/taxRate/edit'],

	'commerce/settings/ordersettings' => ['action' => 'commerce/orderSettings/edit'],
	'commerce/settings/paymentmethods' => ['action' => 'commerce/paymentMethod/index'],
	'commerce/settings/paymentmethods/(?P<class>\w+)' => ['action' => 'commerce/paymentMethod/edit'],

	'commerce/settings/shippingmethods' => ['action' => 'commerce/shippingMethod/index'],
	'commerce/settings/shippingmethods/new' => ['action' => 'commerce/shippingMethod/edit'],
	'commerce/settings/shippingmethods/(?P<id>\d+)' => ['action' => 'commerce/shippingMethod/edit'],
	'commerce/settings/shippingmethods/(?P<methodId>\d+)/shippingrules/new' => ['action' => 'commerce/shippingRule/edit'],
	'commerce/settings/shippingmethods/(?P<methodId>\d+)/shippingrules/(?P<ruleId>\d+)' => ['action' => 'commerce/shippingRule/edit'],
	'commerce/settings/emails' => ['action' => 'commerce/email/index'],
	'commerce/settings/emails/new' => ['action' => 'commerce/email/edit'],
	'commerce/settings/emails/(?P<id>\d+)' => ['action' => 'commerce/email/edit'],

	'commerce/settings/orderstatuses' => ['action' => 'commerce/orderStatus/index'],
	'commerce/settings/orderstatuses/new' => ['action' => 'commerce/orderStatus/edit'],
	'commerce/settings/orderstatuses/(?P<id>\d+)' => ['action' => 'commerce/orderStatus/edit'],


	// Promotions
	'commerce/promotions/sales' => ['action' => 'commerce/sale/index'],
	'commerce/promotions/sales/new' => ['action' => 'commerce/sale/edit'],
	'commerce/promotions/sales/(?P<id>\d+)' => ['action' => 'commerce/sale/edit'],
	'commerce/promotions/discounts' => ['action' => 'commerce/discount/index'],
	'commerce/promotions/discounts/new' => ['action' => 'commerce/discount/edit'],
	'commerce/promotions/discounts/(?P<id>\d+)' => ['action' => 'commerce/discount/edit'],

	// Customers
	'commerce/customers' => ['action' => 'commerce/customer/index'],
	'commerce/customers/(?P<id>\d+)' => ['action' => 'commerce/customer/edit'],
	'commerce/customers/(?P<customerId>\d+)/addresses/(?P<id>\d+)' => ['action' => 'commerce/address/edit'],
];