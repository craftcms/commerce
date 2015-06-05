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

This will enable PHP Storm IDE features for services like `craft()->market_product->method()`

```php
 * @property Market_AddressService         $market_address
 * @property Market_CartService            $market_cart
 * @property Market_CountryService         $market_country
 * @property Market_CustomerService        $market_customer
 * @property Market_DiscountService        $market_discount
 * @property Market_EmailService           $market_email
 * @property Market_GatewayService         $market_gateway
 * @property Market_LineItemService        $market_lineItem
 * @property Market_OptionTypeService      $market_optionType
 * @property Market_OptionValueService     $market_optionValue
 * @property Market_OrderAdjustmentService $market_orderAdjustment
 * @property Market_OrderHistoryService    $market_orderHistory
 * @property Market_OrderService           $market_order
 * @property Market_OrderTypeService       $market_orderType
 * @property Market_OrderStatusService     $market_orderStatus
 * @property Market_PaymentMethodService   $market_paymentMethod
 * @property Market_PaymentService         $market_payment
 * @property Market_ProductService         $market_product
 * @property Market_ProductTypeService     $market_productType
 * @property Market_SaleService            $market_sale
 * @property Market_SeedService            $market_seed
 * @property Market_SettingsService        $market_settings
 * @property Market_ShippingMethodService  $market_shippingMethod
 * @property Market_ShippingRuleService    $market_shippingRule
 * @property Market_StateService           $market_state
 * @property Market_TaxCategoryService     $market_taxCategory
 * @property Market_TaxRateService         $market_taxRate
 * @property Market_TaxZoneService         $market_taxZone
 * @property Market_TransactionService     $market_transaction
 * @property Market_VariantService         $market_variant
```
