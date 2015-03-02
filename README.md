# Market for Craft CMS

This README is designed to be consumed by developers of Market Commerce.

## Code License
Copyright © 2015 Luke Holder
See LICENSE.md

# Documentation

The `docs/phpdoc` is where Sami phpdoc docs are generated
The `docs/guide` will be where static site user guide is gnerated


## Code Hint Helpers for PHP Storm

Add this code into PHP-doc of Craft CMS WebApp.php class.

This will enable PHP Storm IDE features for services like `craft()->market_product->method()`

```php
 * @property Market_AddressService         $market_address
 * @property Market_CartService            $market_cart
 * @property Market_CountryService         $market_country
 * @property Market_CustomerService        $market_customer
 * @property Market_DiscountService        $market_discount
 * @property Market_GatewayService         $market_gateway
 * @property Market_LineItemService        $market_lineItem
 * @property Market_OptionTypeService      $market_optionType
 * @property Market_OptionValueService     $market_optionValue
 * @property Market_OrderAdjustmentService $market_orderAdjustment
 * @property Market_OrderService           $market_order
 * @property Market_OrderTypeService       $market_orderType
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