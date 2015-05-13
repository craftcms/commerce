<?php

return [
	'market'                                                                                  => ['action' => 'market/dashboard/index'],

	'market/settings'                                                                         => ['action' => 'market/settings/index'],
	'market/settings/global'                                                                  => ['action' => 'market/settings/edit'],

	'market/settings/taxcategories'                                                           => ['action' => 'market/taxCategory/index'],
	'market/settings/taxcategories/new'                                                       => ['action' => 'market/taxCategory/edit'],
	'market/settings/taxcategories/(?P<id>\d+)'                                               => ['action' => 'market/taxCategory/edit'],

	'market/settings/countries'                                                               => ['action' => 'market/country/index'],
	'market/settings/countries/new'                                                           => ['action' => 'market/country/edit'],
	'market/settings/countries/(?P<id>\d+)'                                                   => ['action' => 'market/country/edit'],

	'market/settings/states'                                                                  => ['action' => 'market/state/index'],
	'market/settings/states/new'                                                              => ['action' => 'market/state/edit'],
	'market/settings/states/(?P<id>\d+)'                                                      => ['action' => 'market/state/edit'],

	'market/settings/taxzones'                                                                => ['action' => 'market/taxZone/index'],
	'market/settings/taxzones/new'                                                            => ['action' => 'market/taxZone/edit'],
	'market/settings/taxzones/(?P<id>\d+)'                                                    => ['action' => 'market/taxZone/edit'],

	'market/settings/taxrates'                                                                => ['action' => 'market/taxRate/index'],
	'market/settings/taxrates/new'                                                            => ['action' => 'market/taxRate/edit'],
	'market/settings/taxrates/(?P<id>\d+)'                                                    => ['action' => 'market/taxRate/edit'],

	// Product Routes
	'market/products'                                                                         => ['action' => 'market/product/productIndex'],
	'market/products/(?P<productTypeHandle>{handle})/new'                                     => ['action' => 'market/product/editProduct'],
	'market/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)'                      => ['action' => 'market/product/editProduct'],
	'market/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)/variants/new'         => ['action' => 'market/variant/edit'],
	'market/products/(?P<productTypeHandle>{handle})/(?P<productId>\d+)/variants/(?P<id>\d+)' => ['action' => 'market/variant/edit'],

	'market/settings/producttypes'                                                            => ['action' => 'market/productType/index'],
	'market/settings/producttypes/(?P<productTypeId>\d+)'                                     => ['action' => 'market/productType/editProductType'],
	'market/settings/producttypes/new'                                                        => ['action' => 'market/productType/editProductType'],

	'market/settings/optiontypes'                                                             => ['action' => 'market/optionType/index'],
	'market/settings/optiontypes/(?P<optionTypeId>\d+)'                                       => ['action' => 'market/optionType/editOptionType'],
	'market/settings/optiontypes/new'                                                         => ['action' => 'market/optionType/editOptionType'],

	// Order Routes
	'market/orders'                                                                           => ['action' => 'market/order/orderIndex'],
	'market/orders/(?P<orderTypeHandle>{handle})/new'                                         => ['action' => 'market/order/editOrder'],
	'market/orders/(?P<orderTypeHandle>{handle})/(?P<orderId>\d+)'                            => ['action' => 'market/order/editOrder'],

	'market/settings/ordertypes'                                                              => ['action' => 'market/orderType/index'],
	'market/settings/ordertypes/(?P<orderTypeId>\d+)'                                         => ['action' => 'market/orderType/editorderType'],
	'market/settings/ordertypes/new'                                                          => ['action' => 'market/orderType/editOrderType'],

	'market/settings/paymentmethods'                                                          => ['action' => 'market/paymentMethod/index'],
	'market/settings/paymentmethods/(?P<class>\w+)'                                           => ['action' => 'market/paymentMethod/edit'],

	'market/promotions/sales'                                                                   => ['action' => 'market/sale/index'],
	'market/promotions/sales/new'                                                               => ['action' => 'market/sale/edit'],
	'market/promotions/sales/(?P<id>\d+)'                                                       => ['action' => 'market/sale/edit'],

	'market/promotions/discounts'                                                               => ['action' => 'market/discount/index'],
	'market/promotions/discounts/new'                                                           => ['action' => 'market/discount/edit'],
	'market/promotions/discounts/(?P<id>\d+)'                                                   => ['action' => 'market/discount/edit'],

	'market/settings/shippingmethods'                                                         => ['action' => 'market/shippingMethod/index'],
	'market/settings/shippingmethods/new'                                                     => ['action' => 'market/shippingMethod/edit'],
	'market/settings/shippingmethods/(?P<id>\d+)'                                             => ['action' => 'market/shippingMethod/edit'],

	'market/settings/shippingrules'                                                           => ['action' => 'market/shippingRule/index'],
	'market/settings/shippingrules/new'                                                       => ['action' => 'market/shippingRule/edit'],
	'market/settings/shippingrules/(?P<id>\d+)'                                               => ['action' => 'market/shippingRule/edit'],

	'market/settings/emails'                                                                  => ['action' => 'market/email/index'],
	'market/settings/emails/new'                                                              => ['action' => 'market/email/edit'],
	'market/settings/emails/(?P<id>\d+)'                                                      => ['action' => 'market/email/edit'],

    'market/settings/ordertypes/(?P<orderTypeId>\d+)/orderstatuses/new'                       => ['action' => 'market/orderStatus/edit'],
    'market/settings/ordertypes/(?P<orderTypeId>\d+)/orderstatuses/(?P<id>\d+)'               => ['action' => 'market/orderStatus/edit'],
];