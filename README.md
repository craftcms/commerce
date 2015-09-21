# Craft Commerce

This README is designed to be consumed by developers of Craft Commerce,
not end users.

# Code License
Copyright 2015 Pixel & Tonic, Inc. All rights reserved. See LICENSE.md

# Documentation Generation

To generate a phpdoc documentation:

1. `cd docs/phpdoc`
2. `curl -O http://get.sensiolabs.org/sami.phar`
3. `php sami.phar update config.php -v`

Then open the `build/index.html` file in the browser. In chrome the search sidebar will not
show up due to security issues for local files. Use firefox. Chrome does not have this issue when
served from a webserver.

## Code Hint Helpers for PHP Storm

Add this code block into the phpdoc of Craft's WebApp.php class.

This will enable PHP Storm IDE features for services like `craft()->commerce_product->method()`

```php
 * @property Commerce_AddressService         $commerce_address
 * @property Commerce_CartService            $commerce_cart
 * @property Commerce_CountryService         $commerce_country
 * @property Commerce_CustomerService        $commerce_customer
 * @property Commerce_DiscountService        $commerce_discount
 * @property Commerce_EmailService           $commerce_email
 * @property Commerce_GatewayService         $commerce_gateway
 * @property Commerce_LineItemService        $commerce_lineItem
 * @property Commerce_OrderAdjustmentService $commerce_orderAdjustment
 * @property Commerce_OrderHistoryService    $commerce_orderHistory
 * @property Commerce_OrderService           $commerce_order
 * @property Commerce_OrderSettingsService   $commerce_orderSettings
 * @property Commerce_OrderStatusService     $commerce_orderStatus
 * @property Commerce_PaymentMethodService   $commerce_paymentMethod
 * @property Commerce_PaymentService         $commerce_payment
 * @property Commerce_ProductService         $commerce_product
 * @property Commerce_ProductTypeService     $commerce_productType
 * @property Commerce_SaleService            $commerce_sale
 * @property Commerce_SeedService            $commerce_seed
 * @property Commerce_SettingsService        $commerce_settings
 * @property Commerce_ShippingMethodService  $commerce_shippingMethod
 * @property Commerce_ShippingRuleService    $commerce_shippingRule
 * @property Commerce_StateService           $commerce_state
 * @property Commerce_TaxCategoryService     $commerce_taxCategory
 * @property Commerce_TaxRateService         $commerce_taxRate
 * @property Commerce_TaxZoneService         $commerce_taxZone
 * @property Commerce_TransactionService     $commerce_transaction
 * @property Commerce_VariantService         $commerce_variant
```
