# Release Notes for Craft Commerce

## 3.4.22 - 2023-05-31

- Fixed a bug where querying for variants could yield unexpected results. ([#636](https://github.com/craftcms/feed-me/issues/636))

## 3.4.21 - 2023-05-03

- Craft Commerce now requires Dompdf 2.0.0 or later.

## 3.4.20.1 - 2023-03-21

### Fixed
- Fixed a PHP error that occurred when using a third party shipping method. ([#3093](https://github.com/craftcms/commerce/issues/3093))

## 3.4.20 - 2023-02-21

- Fixed a bug that could occur when updating to Commerce 3.
- Fixed a bug that could cause a large number of shipping rule category queries.
- Fixed a PHP error that occurred when eager-loading variant fields.
- Fixed a PHP error that occurred when calling `craft\commerce\services\ProductTypes::getEditableProductTypes()` while not signed in. 

## 3.4.19 - 2022-11-23

### Fixed
- Fixed a bug where carts weren’t getting default billing and shipping addresses set after login when `autoSetNewCartAddresses` was enabled. ([#2903](https://github.com/craftcms/commerce/issues/2903))
- Fixed an error that could occur when purging inactive carts.

## 3.4.18 - 2022-10-26

### Deprecated
- Deprecated `craft\commerce\elements\Order::getShippingMethod()`. `$shippingMethodName` and `$shippingMethodHandle` should be used instead.

### Fixed
- Fixed a bug where custom shipping methods weren’t applying to orders properly. ([#2986](https://github.com/craftcms/commerce/issues/2986))
- Fixed a bug where passing an invalid product type handle into product queries’ `type` params wouldn’t have any effect. ([#2966](https://github.com/craftcms/commerce/issues/2966))

## 3.4.17.2 - 2022-09-16

### Fixed
- Fixed a bug where partial elements were not being deleted during garbage collection.
- Fixed a bug where orders’ item subtotals weren’t being saved to the database.

## 3.4.17.1 - 2022-08-30

### Fixed
- Fixed a bug where the incorrect version number was being shown after updating.

## 3.4.17 - 2022-08-30

### Deprecated
- Deprecated `craft\commerce\services\Orders::pruneDeletedField()`.
- Deprecated `craft\commerce\services\ProductType::pruneDeletedField()`.
- Deprecated `craft\commerce\services\Subscriptions::pruneDeletedField()`.

### Fixed
- Fixed a bug where condition formula results were being cached incorrectly. ([#2842](https://github.com/craftcms/commerce/issues/2842))
- Fixed a bug where not all project config changes would be applied if a field or site was deleted. ([craftcms/cms#9567](https://github.com/craftcms/cms/issues/9567))

## 3.4.16 - 2022-08-05

### Fixed
- Fixed a bug where address zone condition formulas were being cached incorrectly. ([#2842](https://github.com/craftcms/commerce/issues/2842))
- Fixed a bug where querying for orders by email would return incorrect results on PostgreSQL.
- Fixed a bug where clearing a date from a discount would also clear out its usage count. ([#2819](https://github.com/craftcms/commerce/issues/2819))

## 3.4.15 - 2022-05-16

### Changed
- It’s now possible to disable the default variant on the Edit Product page.

### Fixed
- Fixed a bug where it wasn’t possible to navigate the color field with the keyboard on the Edit Order Status page. ([#2601](https://github.com/craftcms/commerce/issues/2601))
- Fixed a bug where it was possible to attempt a payment for a cart with unsaved changes on the Edit Order page. ([#2795](https://github.com/craftcms/commerce/issues/2795))

## 3.4.14 - 2022-04-06

### Fixed
- Fixed a bug where eager-loaded line items weren’t getting returned in a consistent order. ([#2740](https://github.com/craftcms/commerce/issues/2740))
- Fixed a bug where it wasn’t possible to remove an address on the Edit Order page. ([#2745](https://github.com/craftcms/commerce/issues/2745))
- Fixed a bug where the First Name and Last Name fields weren’t shown in payment modals on the Edit Order page, when using the Dummy gateway.

## 3.4.13 - 2022-03-24

### Changed
- Condition formulas now support `abs`, `filter`, `first`, `map`, and `merge` filters. ([#2744](https://github.com/craftcms/commerce/issues/2744))

### Fixed
- Fixed a bug where some order history records were getting deleted along with guest customer records during garbage collection. ([#2727](https://github.com/craftcms/commerce/issues/2727))

## 3.4.12 - 2022-03-16

### Added
- Added support for PHP 8.1.

### Changed
- `craft\commerce\models\ProductType` now supports `EVENT_DEFINE_BEHAVIORS`. ([#2715](https://github.com/craftcms/commerce/issues/2715))

### Fixed
- Fixed a bug where collapsed variant blocks weren’t showing the correct preview text on Edit Product pages.
- Fixed a bug where `craft\commerce\errors\ProductTypeNotFoundException` had the wrong namespace (`craft\errors`) and wasn’t autoloadable with Composer 2.

## 3.4.11 - 2022-02-09

### Changed
- Improved memory usage for `craft\commerce\services\LineItems::getAllLineItemsByOrderId()`.  ([#2673](https://github.com/craftcms/commerce/issues/2673))

### Fixed
- Fixed a bug that could occur when attempting to create a customer on the Edit Order page. ([#2671](https://github.com/craftcms/commerce/issues/2671))
- Fixed a bug where the shipping method name wasn’t getting updated in the Edit Order page sidebar if the shipping method was changed. ([#2682](https://github.com/craftcms/commerce/issues/2682))
- Fixed a bug where `Addresses::getStoreLocation()` could return an address with `isStoreLocation` set to `false`. ([#2688](https://github.com/craftcms/commerce/issues/2688))
- Fixed a bug where Edit State pages included a breadcrumb that linked to a 404. ([#2692](https://github.com/craftcms/commerce/pull/2692))
- Fixed an error that could occur when saving a discount. ([#2505](https://github.com/craftcms/commerce/issues/2505))

## 3.4.10.1 - 2022-01-13

### Fixed
- Fixed a bug where `craft\commerce\models\LiteTaxSettings::getTaxRateAsPercent()` wasn’t returning a value.

## 3.4.10 - 2022-01-12

### Added
- It’s now possible to completely disable the Donation purchasable. ([#2374](https://github.com/craftcms/commerce/discussions/2374))
- Added support for searching orders by line item description. ([#2658](https://github.com/craftcms/commerce/pull/2658))
- Added `craft\commerce\elements\Order::isPaymentAmountPartial()`.
- Added `craft\commerce\helpers\Localization`.

### Fixed
- Fixed a bug where gateways’ `supportsPartialPayment()` methods weren’t being respected.
- Fixed an error that could occur when saving a discount. ([#2660](https://github.com/craftcms/commerce/issues/2660))
- Fixed a bug where partial payment errors weren’t getting returned correctly for Ajax requests to `commerce/payments/pay`.
- Fixed an error that could occur when trying to refund an order. ([#2642](https://github.com/craftcms/commerce/pull/2642))
- Fixed a bug where tax rates weren’t properly supporting localized number formats.

### Security
- Fixed XSS vulnerabilities.

## 3.4.9.3 - 2021-12-23

### Fixed
- Fixed a bug where it wasn’t possible to scroll transactions’ gateway response data on View Order pages. ([#2639](https://github.com/craftcms/commerce/issues/2639))
- Fixed a bug where it wasn’t possible to save sales.

## 3.4.9.2 - 2021-12-15

### Fixed
- Fixed an error that occurred when loading the Order Edit page. ([#2640](https://github.com/craftcms/commerce/issues/2640))

## 3.4.9.1 - 2021-12-15

### Changed
- Craft Commerce now requires Craft CMS 3.7.25 or later. ([#2638](https://github.com/craftcms/commerce/issues/2638))

## 3.4.9 - 2021-12-14

### Added
- Discounts and sales now have “All purchasables”, “All categories”, and “All customers” settings. ([#2615](https://github.com/craftcms/commerce/issues/2615))

### Changed
- Product indexes now use a “Product” header column heading by default, rather than “Title”.

### Fixed
- Fixed a bug where adjustment snapshots were removed when recalculating a completed order.
- Fixed a bug where email previews included control panel assets. ([#2632](https://github.com/craftcms/commerce/issues/2632))

## 3.4.8 - 2021-11-25

### Added
- Added `craft\commerce\controllers\ProductsController::enforceEditProductPermissions()`.
- Added `craft\commerce\controllers\ProductsController::enforceDeleteProductPermissions()`.
- Added `craft\commerce\controllers\ProductsPreviewController::enforceEditProductPermissions()`.
- Added `craft\commerce\controllers\SubscriptionsController::enforceEditSubscriptionPermissions()`.

### Changed
- Improved the performance of order saving.
- Formula condition results are now cached.
- Products now support `EVENT_DEFINE_IS_EDITABLE` and `EVENT_DEFINE_IS_DELETABLE`. ([#2606](https://github.com/craftcms/commerce/issues/2606))
- Subscriptions now support `EVENT_DEFINE_IS_EDITABLE`.

## Deprecated
- Deprecated `craft\commerce\controllers\ProductsController::enforceProductPermissions()`.
- Deprecated `craft\commerce\controllers\ProductsPreviewController::enforceProductPermissions()`.
- Deprecated `craft\commerce\controllers\ProductsPreviewController::actionSaveProduct()`.

### Fixed
- Fixed a bug where active/inactive cart queries weren’t factoring the system time zone properly. ([#2601](https://github.com/craftcms/commerce/issues/2601))
- Fixed a bug where it was possible to save a product without any variants. ([#2612](https://github.com/craftcms/commerce/issues/2612))
- Fixed a bug where the First Name and Last Name fields within payment modals weren’t immediately visible. ([#2603](https://github.com/craftcms/commerce/issues/2603))
- Fixed a bug where the “Billing Details URL” subscription setting didn’t fully support being set to an environment variable. ([#2571](https://github.com/craftcms/commerce/pull/2571))

## 3.4.7 - 2021-11-03

### Deprecated
- Deprecated `craft\commerce\models\ProductType::getLineItemFormat()` and `setLineItemFormat()`.

### Fixed
- Fixed a bug where products’ Variants fields could be added to the field layout twice.
- Fixed a bug where PDFs could be rendered incorrectly. ([#2599](https://github.com/craftcms/commerce/issues/2599))
- Fixed an error that that could occur when instantiating a `craft\commerce\elements\Order` object. ([#2602](https://github.com/craftcms/commerce/issues/2602))

### Security
- Fixed XSS vulnerabilities.

## 3.4.6 - 2021-10-20

### Added
- Added `craft\commerce\services\PaymentSources::getPaymentSourceByTokenAndGatewayId()`.

### Changed
- Improved the styling and behavior of the example templates.

### Fixed
- Fixed a bug where purging inactive carts did not respect time zones. ([#2588](https://github.com/craftcms/commerce/issues/2588))
- Fixed a bug where it was possible to manage discounts when using the Lite edition. ([#2590](https://github.com/craftcms/commerce/issues/2590))

## 3.4.5 - 2021-10-13

### Added
- Added `craft\commerce\services\Transactions::getTransactionByReference()`.

### Fixed
- Fixed a bug where shipping rules could never match when the cart was empty. ([#2583](https://github.com/craftcms/commerce/issues/2583))
- Fixed a bug where a default shipping method was not being set per the `autoSetCartShippingMethodOption` setting. ([#2584](https://github.com/craftcms/commerce/issues/2584))

## 3.4.4 - 2021-10-07

### Added
- Added the `autoSetCartShippingMethodOption` config setting.

### Changed
- The `commerce/cart/update-cart` action now supports a `clearLineItems` param.

### Fixed
- Fixed a bug where addresses weren’t getting copied to orders correctly. ([#2555](https://github.com/craftcms/commerce/issues/2555))

## 3.4.3 - 2021-09-22

### Fixed
- Fixed a bug where discounts’ purchasable conditions weren’t applying to products when they were initially added to the cart. ([#2559](https://github.com/craftcms/commerce/issues/2559)) 
- Fixed a bug where carts weren’t getting recalculated when force-saved. ([#2560](https://github.com/craftcms/commerce/issues/2560))
- Fixed a bug where Commerce models’ `defineRules()` methods were declared as `public` instead of `protected`.
- Fixed a bug where new Commerce installs were getting a `sendCartInfo` column added to their `commerce_gateways` table, which isn’t needed.

### Security
- Fixed XSS vulnerabilities.

## 3.4.2 - 2021-08-11

### Changed
- Discount condition formulas now allow `|date` filters. ([#2505](https://github.com/craftcms/commerce/issues/2505))
- Orders now include billing and shipping addresses in search their keywords.
- The `registerUserOnOrderComplete` order parameter is now honored when completing an order from the control panel. ([#2503](https://github.com/craftcms/commerce/issues/2503))

### Fixed
- Fixed a bug where zero-value payment amounts would be ignored in favor of the outstanding balance, if using an alternative currency. ([#2501](https://github.com/craftcms/commerce/issues/2501))
- Fixed a bug where it wasn't possible to modify the address query from `craft\commerce\services\Addresses::EVENT_BEFORE_PURGE_ADDRESSES` event handlers.
- Fixed a bug where `craft\commerce\services\Formulas::validateFormulaSyntax()` wasn't working properly.

## 3.4.1 - 2021-07-26

### Changed
- Improved the performance of order recalculation.

### Fixed
- Fixed a bug where discount queries on coupon codes were case sensitive. ([#2249](https://github.com/craftcms/commerce/issues/2249))
- Fixed a SQL error that could occur during a migration with a database using a table prefix. ([#2497](https://github.com/craftcms/commerce/issues/2497))

## 3.4.0.3 - 2021-07-21

### Fixed
- Fixed a bug where it wasn't possible for customers to select shipping methods registered by plugins. ([#2278](https://github.com/craftcms/commerce/issues/2278))
- Fixed a PHP error that could occur when accessing an orders’ shipping method that was registered by plugins. ([#2279](https://github.com/craftcms/commerce/issues/2279))

## 3.4.0.2 - 2021-07-19

### Fixed
- Fixed a bug where it wasn't possible for customers to select shipping methods registered by plugins. ([#2273](https://github.com/craftcms/commerce/issues/2273))

## 3.4.0.1 - 2021-07-14

### Fixed
- Fixed a couple UI bugs on Edit Order pages. ([#2270](https://github.com/craftcms/commerce/issues/2270))
- Fixed a bug where `orderSiteId` and `orderLanguage` order query params woren’t working correctly. ([#2272](https://github.com/craftcms/commerce/issues/2272))

## 3.4.0 - 2021-07-13

### Added
- Added the ability to download multiple orders’ PDFs as a single, combined PDF from the Orders index page. ([#1785](https://github.com/craftcms/commerce/issues/1785))
- Added the ability to disable included tax removal. ([#1881](https://github.com/craftcms/commerce/issues/1881))
- Added the “Revenue Options” setting to the Top Products widget. ([#1919](https://github.com/craftcms/commerce/issues/1919))
- Added the ability to bulk-delete discounts from the Discounts index page. ([#2172](https://github.com/craftcms/commerce/issues/2172))
- Added the ability to bulk-delete sales from the Sales index page.
- Added the `cp.commerce.discounts.index`, `cp.commerce.discounts.edit`, `cp.commerce.discounts.edit.content`, and `cp.commerce.discounts.edit.details` template hooks. ([#2173](https://github.com/craftcms/commerce/issues/2173))
- Added the `cp.commerce.sales.index`, `cp.commerce.sales.edit`, `cp.commerce.sales.edit.content`, and `cp.commerce.sales.edit.details` template hooks. ([#2173](https://github.com/craftcms/commerce/issues/2173))
- Added `craft\commerce\base\Plan::$dateCreated`.
- Added `craft\commerce\base\Plan::$dateUpdated`.
- Added `craft\commerce\elements\Order::hasShippableItems()`.
- Added `craft\commerce\models\Address::$dateCreated`.
- Added `craft\commerce\models\Address::$dateUpdated`.
- Added `craft\commerce\models\Country::$dateCreated`.
- Added `craft\commerce\models\Country::$dateUpdated`.
- Added `craft\commerce\models\Customer::$dateCreated`.
- Added `craft\commerce\models\Customer::$dateUpdated`.
- Added `craft\commerce\models\LineItem::getIsShippable()`.
- Added `craft\commerce\models\PaymentCurrency::$dateCreated`.
- Added `craft\commerce\models\PaymentCurrency::$dateUpdated`.
- Added `craft\commerce\models\Sale::$dateCreated`.
- Added `craft\commerce\models\Sale::$dateUpdated`.
- Added `craft\commerce\models\ShippingAddressZone::$dateCreated`.
- Added `craft\commerce\models\ShippingAddressZone::$dateUpdated`.
- Added `craft\commerce\models\ShippingCategory::$dateCreated`.
- Added `craft\commerce\models\ShippingCategory::$dateUpdated`.
- Added `craft\commerce\models\ShippingMethod::$dateCreated`.
- Added `craft\commerce\models\ShippingMethod::$dateUpdated`.
- Added `craft\commerce\models\ShippingRule::$dateCreated`.
- Added `craft\commerce\models\ShippingRule::$dateUpdated`.
- Added `craft\commerce\models\State::$dateCreated`.
- Added `craft\commerce\models\State::$dateUpdated`.
- Added `craft\commerce\models\TaxAddressZone::$dateCreated`.
- Added `craft\commerce\models\TaxAddressZone::$dateUpdated`.
- Added `craft\commerce\models\TaxCategory::$dateCreated`.
- Added `craft\commerce\models\TaxCategory::$dateUpdated`.
- Added `craft\commerce\models\TaxRate::$dateCreated`.
- Added `craft\commerce\models\TaxRate::$dateUpdated`.
- Added `craft\commerce\models\TaxRate::$removeIncluded`.
- Added `craft\commerce\models\TaxRate::$removeVatIncluded`.
- Added `craft\commerce\stats\TopProducts::$revenueOptions`.
- Added `craft\commerce\stats\TopProducts::REVENUE_OPTION_DISCOUNT`.
- Added `craft\commerce\stats\TopProducts::REVENUE_OPTION_SHIPPING`.
- Added `craft\commerce\stats\TopProducts::REVENUE_OPTION_TAX_INCLUDED`.
- Added `craft\commerce\stats\TopProducts::REVENUE_OPTION_TAX`.
- Added `craft\commerce\stats\TopProducts::TYPE_QTY`.
- Added `craft\commerce\stats\TopProducts::TYPE_REVENUE`.
- Added `craft\commerce\stats\TopProducts::createAdjustmentsSubQuery()`.
- Added `craft\commerce\stats\TopProducts::getAdjustmentsSelect()`.
- Added `craft\commerce\stats\TopProducts::getGroupBy()`.
- Added `craft\commerce\stats\TopProducts::getOrderBy()`.
- Added libmergepdf.

### Changed
- Craft Commerce now requires Craft CMS 3.7 or later.
- Product slideouts now include the full field layout and meta fields. ([#2205](https://github.com/craftcms/commerce/pull/2205))
- Discounts now have additional user group condition options. ([#220](https://github.com/craftcms/commerce/issues/220))
- It’s now possible to select any shipping method for a completed order. ([#1521](https://github.com/craftcms/commerce/issues/1521))
- The order field layout no longer validates if it contains a field called `billingAddress`, `customer`, `estimatedBillingAddress`, `estimatedShippingAddress`, `paymentAmount`, `paymentCurrency`, `paymentSource`, `recalculationMode` or `shippingAddress`.
- Product field layouts no longer validate if they contain a field called `cheapestVariant`, `defaultVariant` or `variants`.
- Variant field layouts no longer validate if they contain a field called `description`, `price`, `product` or `sku`.
- Order notices are now cleared when orders are completed. ([#2116](https://github.com/craftcms/commerce/issues/2116))
- Donations, orders, products, and variants now support `EVENT_DEFINE_IS_EDITABLE` and `EVENT_DEFINE_IS_DELETABLE`. ([craftcms/cms#8023](https://github.com/craftcms/cms/issues/8023))
- Address, customer, country, state, payment currency, promotion, shipping, subscription plan, and tax edit pages now display date meta info.
- Emails are now added to the queue with a higher priority than most jobs. ([#2157](https://github.com/craftcms/commerce/issues/2157))
- Improved the performance of store location address retrieval. ([#2238](https://github.com/craftcms/commerce/issues/2238))

### Fixed
- Fixed a bug where discounts weren’t displaying validation errors for the “Per Email Address Discount Limit” field. ([#1455](https://github.com/craftcms/commerce/issues/1455))
- Fixed a bug where orders that didn‘t contain any shippable items still required a shipping method selection. ([#2204](https://github.com/craftcms/commerce/issues/2204))
- Fixed a UI bug with Order Edit page template hooks. ([#2148](https://github.com/craftcms/commerce/issues/2148))
- Fixed a PHP error that could occur when adding multiple items to the cart at once.

## 3.3.5.1 - 2021-07-07

### Fixed
- Fixed a bug where the customer search box was showing as “undefined” on Edit Order pages. ([#2247](https://github.com/craftcms/commerce/issues/2247))
- Fixed a bug where it wasn’t possible to query for products by `productTypeId` via GraphQL. ([#2248](https://github.com/craftcms/commerce/issues/2248))

## 3.3.5 - 2021-07-06

### Added
- It’s now possible to copy a subscription’s reference from its edit page.
- It’s now possible to search for orders by their shipping and billing addresses.

### Fixed
- Fixed a bug where long subscription references would break the meta layout on Edit Subscription pages. ([#2211](https://github.com/craftcms/commerce/issues/2211))
- Fixed a bug where non-promotable purchasables could have order-level discounts applied. ([#2180](https://github.com/craftcms/commerce/issues/2180))
- Fixed a bug where it wasn’t possible to change the base currency. ([#2221](https://github.com/craftcms/commerce/issues/2221))
- Fixed a bug where primary addresses weren’t being copied to new guest customers’ address books. ([#2224](https://github.com/craftcms/commerce/issues/2224))
- Fixed a bug where months were missing in Past Year stat queries.
- Fixed a bug where the outstanding payment amount due in an alternate currency wasn’t getting rounded after conversion, preventing orders from being barked as fully paid. ([#2222](https://github.com/craftcms/commerce/issues/2222))
- Fixed a bug where some PDF settings weren’t getting migrated properly when updating from an earlier version of Commerce than 3.2.0. ([#2213](https://github.com/craftcms/commerce/issues/2213))
- Fixed a PHP 8 compatibility bug. ([#2198](https://github.com/craftcms/commerce/issues/2198))

## 3.3.4.1 - 2021-06-16

### Fixed
- Fixed a bug where the database schema for new Craft Commerce installations wasn’t consistent with older installations.

## 3.3.4 - 2021-06-15

### Added
- Added `craft\commerce\elements\db\VariantQuery::hasUnlimitedStock()`. ([#2188](https://github.com/craftcms/commerce/issues/2188))
- Added `craft\commerce\models\LineItem::getIsTaxable()`.

### Changed
- Improved the performance of determining an order’s available discounts. ([#1744](https://github.com/craftcms/commerce/issues/1744))

### Fixed
- Fixed a bug that could occur when rebuilding the project config. ([#2194](https://github.com/craftcms/commerce/issues/2194))
- Fixed a bug where it was possible for an order to use a disabled payment gateway. ([#2150](https://github.com/craftcms/commerce/issues/2150))
- Fixed a SQL error that could occur when programmatically saving a variant without stock. ([#2186](https://github.com/craftcms/commerce/issues/2186))
- Fixed a bug where a donation marked as non-taxable could still receive tax. ([#2144](https://github.com/craftcms/commerce/pull/2144))
- Fixed a bug where the order field layout’s UID would change on every save. ([#2193](https://github.com/craftcms/commerce/issues/2193))
- Fixed a SQL error that occurred when saving a payment currency without a conversion rate. ([#2149](https://github.com/craftcms/commerce/issues/2149))
- Fixed a bug where discounts weren’t displaying validation errors for the “Per User Discount Limit” field. ([#2176](https://github.com/craftcms/commerce/issues/2176))

## 3.3.3 - 2021-06-01

### Added
- Added the `productCount` and `variantCount` GraphQL queries. ([#1411](https://github.com/craftcms/commerce/issues/1411))

### Changed
- It’s now possible to sort products by their SKUs on product indexes. ([#2167](https://github.com/craftcms/commerce/issues/2167))
- Products now have a `url` field when queried via GraphQL.

### Fixed
- Fixed a bug where it wasn’t possible to customize product search keywords via `EVENT_DEFINE_KEYWORDS`. ([#2142](https://github.com/craftcms/commerce/issues/2142))
- Fixed a bug where the “Add Product to Sale” modal on Edit Product pages could be unresponsive when opened multiple times. ([#2146](https://github.com/craftcms/commerce/issues/2146))
- Fixed an error that could occur if MySQL’s time zone tables weren’t populated yet. ([#2163](https://github.com/craftcms/commerce/issues/2163))
- Fixed a PHP error that could occur when validating a product. ([#2138](https://github.com/craftcms/commerce/issues/2138))

## 3.3.2 - 2021-05-18

### Added
- It’s now possible to create customer addresses in the control panel. ([#1324](https://github.com/craftcms/commerce/issues/1324))
- Added `craft\commerce\events\PurchasableShippableEvent`.
- Added `craft\commerce\services\Purchasables::EVENT_PURCHASABLE_SHIPPABLE`.
- Added `craft\commerce\services\Purchasables::isPurchasableShippable()`.

### Fixed
- Customer search Ajax requests are now cancelled before sending new ones on Edit Order pages. ([#2137](https://github.com/craftcms/commerce/issues/2137))
- Fixed an error that occurred when submitting a blank line item quantity from an Edit Order page, when running PHP 8. ([#2125](https://github.com/craftcms/commerce/issues/2125))
- Fixed a bug where changes to addresses’ State fields on Edit Order pages weren’t persisting. ([#2136](https://github.com/craftcms/commerce/issues/2136))
- Fixed a bug where charts weren’t always displaying the correct data for the date range, when running MySQL. ([#2117](https://github.com/craftcms/commerce/issues/2117))

## 3.3.1.1 - 2021-05-09

### Fixed
- Fixed a bug that caused the billing address to be overridden by the shipping address on order completion. ([#2128](https://github.com/craftcms/commerce/issues/2128))

## 3.3.1 - 2021-05-04

### Added
- Added `craft\commerce\events\RefundTransactionEvent::$refundTransaction`. ([#2081](https://github.com/craftcms/commerce/issues/2081))
- Added `craft\commerce\services\Purchasables::EVENT_PURCHASABLE_AVAILABLE`.
- Added `craft\commerce\services\Purchasables::isPurchasableAvailable()`.

### Changed
- Order condition formulas now include serialized custom field values. ([#2066](https://github.com/craftcms/commerce/issues/2066))
- Replaced `date` to `datetime` filter of `orderHistory.dateCreated` attribute in status history tab in order edit page.

### Fixed
- Fixed a PHP error that occurred when changing a variant from having unlimited stock. ([#2111](https://github.com/craftcms/commerce/issues/2111))
- Fixed a PHP error that occurred when passing the `registerUserOnOrderComplete` parameter to the `commerce/cart/complete` action.
- Fixed a PHP error that occurred when attempting to retrieve an order notice that doesn’t exist. ([#2108](https://github.com/craftcms/commerce/issues/2108))
- Fixed a bug where orders’ address IDs were `null` at the time `EVENT_AFTER_COMPLETE_ORDER` was triggered.
- Fixed a bug where payment source error messages weren’t being returned correctly.

## 3.3.0.1 - 2021-04-26

### Fixed
- Fixed a bug where an incorrect amount could be calculated when paying an outstanding balance in a non-primary currency.
- Fixed a bug where shipping rules were enforcing the “Order Condition Formula” field as required. ([#2098](https://github.com/craftcms/commerce/issues/2098))
- Fixed a bug where Base Discount and Per Item Discount fields could show negative values on the Edit Discount page. ([#2090](https://github.com/craftcms/commerce/issues/2090))

## 3.3.0 - 2021-04-20

### Added
- Added support for partial payments. ([#585](https://github.com/craftcms/commerce/issues/585))
- Carts can now display customer-facing notices on price changes and when items are automatically removed due to going out of stock. ([#2000](https://github.com/craftcms/commerce/pull/2000))
- It’s now possible to set dynamic condition formulas on shipping rules. ([#1959](https://github.com/craftcms/commerce/issues/1959))
- The Orders index page and Edit Order page now have a “Share cart” action, which generates a sharable URL that will load the cart into the user’s session, making it the active cart. ([#1386](https://github.com/craftcms/commerce/issues/1386))
- Shipping rule conditions can now be based on an order’s discounted price, rather than its original price. ([#1948](https://github.com/craftcms/commerce/pull/1948))
- Added the `allowCheckoutWithoutPayment` config setting.
- Added the `allowPartialPaymentOnCheckout` config setting.
- Added the `commerce/cart/complete` action.
- Added `craft\commerce\base\GatewayInterface::supportsPartialPayment()`.
- Added `craft\commerce\base\Gateway::supportsPartialPayment()`.
- Added `craft\commerce\elements\Order::getLoadCartUrl()`.
- Added `craft\commerce\services\Addresses::EVENT_BEFORE_PURGE_ADDRESSES`. ([#1627](https://github.com/craftcms/commerce/issues/1627))
- Added `craft\commerce\services\PaymentCurrencies::convertCurrency()`.
- Added `craft\commerce\test\fixtures\elements\ProductFixture::_getProductTypeIds()`.

### Changed
- Improved the line item editing workflow on the Edit Order page.
- Line item descriptions now link to the purchasable’s edit page in the control panel. ([#2048](https://github.com/craftcms/commerce/issues/2048))
- All front-end controllers now support passing the order number via a `number` param. ([#1970](https://github.com/craftcms/commerce/issues/1970))
- Products are now resaved when a product type’s available tax or shipping categories change. ([#1933](https://github.com/craftcms/commerce/pull/1933))
- Updated Dompdf to 1.0.2.

### Deprecated
- Deprecated `craft\commerce\services\Gateways::getGatewayOverrides()` and the `commerce-gateways.php` config file. Gateway-specific config files should be used instead. ([#1963](https://github.com/craftcms/commerce/issues/1963))

### Fixed
- Fixed a PHP 8 compatibility bug. ([#1987](https://github.com/craftcms/commerce/issues/1987))
- Fixed an error that occurred when passing an unsupported payment currency to `craft\commerce\services\PaymentCurrencies::convert()`.

## 3.2.17.4 - 2021-04-06

### Fixed
- Fixed a bug where line items would disappear from the Edit Order page when their quantity value was cleared. ([#2058](https://github.com/craftcms/commerce/issues/2058))
- Fixed a bug where customers without primary billing and shipping addresses weren’t being shown in the Customers list. ([#2052](https://github.com/craftcms/commerce/issues/2052))

## 3.2.17.3 - 2021-03-18

### Fixed
- Fixed a bug where the “All Totals” column on the Orders index page was showing blank values. ([#2047](https://github.com/craftcms/commerce/pull/2047))

## 3.2.17.2 - 2021-03-17

### Fixed
- Fixed a bug where the `commerce/reset-data` command did not delete addresses. ([#2042](https://github.com/craftcms/commerce/issues/2042))
- Fixed a bug where included tax totals may be incorrect after updating from Commerce 1.
- Fixed a bug where the `success` and `error` keys were missing from `commerce/payments/complete-payment` JSON responses. ([#2043](https://github.com/craftcms/commerce/issues/2043))

## 3.2.17.1 - 2021-03-08

### Changed
- The `generateTransformsBeforePageLoad` config setting is now automatically enabled when rendering emails. ([#2034](https://github.com/craftcms/commerce/issues/2034))
- `craft\commerce\services\Pdfs::EVENT_BEFORE_RENDER_PDF` event handlers can now modify the variables the PDF will be rendered with. ([#2039](https://github.com/craftcms/commerce/issues/2039))

### Fixed
- Fixed a bug where the Orders index page was showing the wrong shipping and billing addresses. ([#1962](https://github.com/craftcms/commerce/issues/1962))
- Fixed a bug where sales were storing incorrect amounts for locales that use a period for the grouping symbol. ([#2029](https://github.com/craftcms/commerce/issues/2029))

## 3.2.17 - 2021-03-03

### Added 
- Added the ability to set a cart’s order site on the Edit Order page. ([#2031](https://github.com/craftcms/commerce/issues/2031))
- Added the `cp.commerce.customers.edit`, `cp.commerce.customers.edit.content`, and `cp.commerce.customers.edit.details` template hooks to the Edit Customer page. ([#2030](https://github.com/craftcms/commerce/issues/2030))

### Fixed
- Fixed a UI bug with the “Order Site” and “Status” fields on the Edit Order page. ([#2023](https://github.com/craftcms/commerce/issues/2023))

### Security
- Fixed an XSS vulnerability.

## 3.2.16 - 2021-02-26

### Fixed
- Fixed a bug where it wasn’t possible to paginate addresses on the Edit Order page. ([#2024](https://github.com/craftcms/commerce/issues/2024))
- Fixed a PHP error that could occur when adding purchasables to a sale from the Edit Product page. ([#1998](https://github.com/craftcms/commerce/issues/1998))
- Fixed a bug where guest customers weren’t being consolidated to the user’s customer. ([#2019](https://github.com/craftcms/commerce/issues/2019))
- Fixed a migration error that could occur when updating from Commerce 2. ([#2022](https://github.com/craftcms/commerce/issues/2022))

## 3.2.15.3 - 2021-02-24

### Fixed
- Fixed a bug where past orders weren’t being consolidated to the user’s customer. ([#2019](https://github.com/craftcms/commerce/issues/2019))

## 3.2.15.2 - 2021-02-18

### Fixed
- Fixed a bug where querying for an empty array on the `productId` variant query param would return all variants.

## 3.2.15.1 - 2021-02-18

### Fixed
- Fixed an error that occurred when deleting products. ([#2009](https://github.com/craftcms/commerce/issues/2009))

## 3.2.15 - 2021-02-17

### Changed
- Carts that only contains non-shipppable items no longer attempt to match any shipping rules. ([#1990](https://github.com/craftcms/commerce/issues/1990))
- Product queries with the `type` or `typeId` param will now only invalidate their `{% cache %}` tags when products of the same type(s) are saved/deleted.
- Variant queries with the `product` or `productId` param will now only invalidate their `{% cache %}` tags when the referenced products are saved/deleted.
- The `commerce/payment-sources/add`, `commerce/subscriptions/subscribe`, `commerce/subscriptions/switch`, `commerce/subscriptions/cancel`, and `commerce/subscriptions/reactivate` actions now accept hashed `successMessage` params. ([#1955](https://github.com/craftcms/commerce/issues/1955))
- `craft\commerce\elements\db\VariantQuery::product` is now write-only.

### Fixed
- Fixed a bug where carts weren’t getting recalculated after their billing address was saved via the `commerce/customer-addresses/save` action. ([#1997](https://github.com/craftcms/commerce/issues/1997))
- Fixed a bug where category shipping rules weren’t remembering their cost overrides when set to `0` . ([#1999](https://github.com/craftcms/commerce/issues/1999))

## 3.2.14.1 - 2021-01-28

### Fixed
- Fixed a UI bug with product dimension inputs on Craft 3.6. ([#1977](https://github.com/craftcms/commerce/issues/1977))

## 3.2.14 - 2021-01-13

### Added
- It is now possible to sort purchasables by `description`, `sku` or `price` when adding a line item on the Edit Order page. ([#1940](https://github.com/craftcms/commerce/issues/1940))
- Added `craft\commerce\elements\db\ProductQuery::defaultPrice()`, `defaultWidth()`, `defaultHeight()`, `defaultLength()`, `defaultWeight()`, and `defaultSku()`. ([#1877](https://github.com/craftcms/commerce/issues/1877))

### Changed
- Purchasables are now sorted by `id` by default when adding a line item to an order on the Edit Order page.

### Fixed
- Fixed a bug where the Edit Order page was listing soft-deleted purchasables when adding a line item. ([#1939](https://github.com/craftcms/commerce/issues/1939))
- Fixed a bug where product indexes’ “Title” columns were getting mislabeled as “ID”. ([#1787](https://github.com/craftcms/commerce/issues/1787))
- Fixed an error that could occur when saving a product, if a price, weight, or dimension field was set to a non-numeric value. ([#1942](https://github.com/craftcms/commerce/issues/1942))
- Fixed a bug where line item prices could show the wrong currency on Edit Order pages. ([#1890](https://github.com/craftcms/commerce/issues/1890))
- Fixed an error that could occur when saving an address. ([#1947](https://github.com/craftcms/commerce/issues/1947))
- Fixed an error that occurred when calling `Plans::getPlansByInformationEntryId()`. ([#1949](https://github.com/craftcms/commerce/issues/1949))
- Fixed a SQL error that occurred when purging customers on MySQL. ([#1958](https://github.com/craftcms/commerce/issues/1958))
- Fixed a SQL error that occurred when retrieving the default line item status on PostgreSQL.

## 3.2.13.2 - 2020-12-15

### Fixed
- Fixed a bug where product URLs were resolving even though the product was not live. ([#1929](https://github.com/craftcms/commerce/pull/1929))

## 3.2.13.1 - 2020-12-15

### Fixed
- Fixed a migration error that could occur when updating from Commerce 3.1 ([#1928](https://github.com/craftcms/commerce/issues/1928))

## 3.2.13 - 2020-12-10

### Added
- Emails and PDFs now have Language settings that can be used to specify the language that should be used, instead of the order’s language. ([#1884](https://github.com/craftcms/commerce/issues/1884))
- Added the `cp.commerce.order.content`, `cp.commerce.order.edit.order-actions`, and `cp.commerce.order.edit.order-secondary-actions` template hooks to the Edit Order page. ([#138](https://github.com/craftcms/commerce/issues/138), [#1269](https://github.com/craftcms/commerce/issues/1269))

### Changed
- Improved the Edit Product page load time by lazy-loading variants’ related sales on scroll. ([#1883](https://github.com/craftcms/commerce/issues/1883))
- The Edit Order page no longer requires orders to have at least one line item to be saved.

### Fixed
- Fixed a bug where Products indexes weren’t displaying `0` stock values. ([#1908](https://github.com/craftcms/commerce/issues/1908))
- Fixed a bug where dates and numbers in order PDFs weren’t always rendered with the order’s locale. ([#1876](https://github.com/craftcms/commerce/issues/1876))
- Fixed a bug where `craft\commerce\models\Address::getAddressLines()` wasn’t including a `businessTaxId` key. ([#1894](https://github.com/craftcms/commerce/issues/1894))
- Fixed a bug where `craft\commerce\services\Discounts::getDiscountByCode()` was returning disabled discounts.
- Fixed a bug where `craft\commerce\models\Address::setAttributes()` wasn’t setting the `businessId` by default. ([#1909](https://github.com/craftcms/commerce/issues/1909))
- Fixed some PostgreSQL compatibility issues.

## 3.2.12 - 2020-11-17

### Added
- Variants now have `priceAsCurrency` and `salePriceAsCurrency` fields when queried via GraphQL. ([#1856](https://github.com/craftcms/commerce/issues/1856))
- Products now have an `defaultPriceAsCurrency` field when queried via GraphQL. ([#1856](https://github.com/craftcms/commerce/issues/1856))

### Changed
- Improved the Edit Order page’s ability to warn against losing unsaved changes. ([#1850](https://github.com/craftcms/commerce/issues/1850))
- All built-in success/fail flash messages are now customizable by passing hashed `successMessage` and `failMessage` params with the request. ([#1871](https://github.com/craftcms/commerce/issues/1871))

### Fixed
- Fixed an error that occurred when attempting to edit a subscription plan, if `allowAdminChanges` was disabled. ([#1857](https://github.com/craftcms/commerce/issues/1857))
- Fixed an error that occurred when attempting to preview an email, if no orders had been completed yet. ([#1858](https://github.com/craftcms/commerce/issues/1858))
- Fixed an error that occurred when adding a new address to a completed order on the Edit Order page, if using PostgreSQL.
- Fixed a bug where template caches weren’t getting invalidated when sales were added or removed. ([#1849](https://github.com/craftcms/commerce/issues/1849))
- Fixed a bug where sales weren’t properly supporting localized number formats.
- Fixed a deprecation warning that occurred in the example templates. ([#1859](https://github.com/craftcms/commerce/issues/1859))

## 3.2.11 - 2020-11-04

### Changed
- Moved subscription plans from `commerce/settings/subscriptions/plans` to `commerce/store-settings/subscription-plans` in the control panel. ([#1846](https://github.com/craftcms/commerce/issues/1846))

### Fixed
- Emails that are prevented from being sent using the `\craft\commerce\services\Emails::EVENT_BEFORE_SEND_MAIL` event are no longer shown as failed jobs on the queue. ([#1842](https://github.com/craftcms/commerce/issues/1842))
- Fixed a PHP error that occurred when creating a new Product with multiple variants. ([#1851](https://github.com/craftcms/commerce/issues/1851))

## 3.2.10.1 - 2020-11-03

### Fixed
- Fixed a PostgreSQL migration issue. ([#1845](https://github.com/craftcms/commerce/pull/1845))

## 3.2.10 - 2020-11-02

### Added
- Added the ability to unset a cart’s selected payment source with the `commerce/cart/update-cart` action. ([#1835](https://github.com/craftcms/commerce/issues/1835))
- Added `craft\commerce\services\Pdfs::EVENT_MODIFY_RENDER_OPTIONS`. ([#1761](https://github.com/craftcms/commerce/issues/1761))

### Fixed
- Fixed a PHP error that occurred when retrieving the field layout for a variant of a deleted product. ([#1830](https://github.com/craftcms/commerce/pull/1830))
- Fixed a bug where restoring a deleted product restored previously-deleted variants. ([#1827](https://github.com/craftcms/commerce/issues/1827))
- Fixed a template error that would occur when creating or editing a tax rate. ([#1841](https://github.com/craftcms/commerce/issues/1841))

## 3.2.9.1 - 2020-10-30

### Fixed
- Fixed a bug where the `commerce_orders` table was missing the `orderSiteId` column on fresh installs. ([#1828](https://github.com/craftcms/commerce/pull/1828))

## 3.2.9 - 2020-10-29

### Added
- Added the ability to track which site an order was placed from.
- Added the “ID” column to the Products index page. ([#1787](https://github.com/craftcms/commerce/issues/1787))
- Added the “Order Site” column to the Orders index page.
- Added the ability to retry failed order status emails. ([#1397](https://github.com/craftcms/commerce/issues/1379))
- Added the ability to change the default tax category right from the Tax Categories index page. ([#1499](https://github.com/craftcms/commerce/issues/1499))
- Added the ability to change the default shipping category right from the Shipping Categories index page.
- Added the ability to update a shipping method’s status right from the Shipping Methods index page.
- All front-end success/fail flash messages are now customizable by passing hashed `successMessage`/`failMessage` params with the request. ([#1801](https://github.com/craftcms/commerce/issues/1801))
- It’s now possible to see purchasables’ snaphsot data when adding line items to an order from the Edit Order page. ([#1792](https://github.com/craftcms/commerce/issues/1792))
- Addresses now show whether they are the primary shipping/billing address for a customer on the Edit Address page. ([#1802](https://github.com/craftcms/commerce/issues/1802))
- Added the `cp.commerce.shippingMethods.edit.content` hook to the `shipping/shippingmethods/_edit.html` template. ([#1819](https://github.com/craftcms/commerce/pull/1819))
- Added `craft\commerce\elements\Order::$orderSiteId`.
- Added `craft\commerce\services\Customers::EVENT_AFTER_SAVE_CUSTOMER_ADDRESS`. ([#1220](https://github.com/craftcms/commerce/issues/1220))
- Added `craft\commerce\services\Customers::EVENT_AFTER_SAVE_CUSTOMER`.
- Added `craft\commerce\services\Customers::EVENT_BEFORE_SAVE_CUSTOMER_ADDRESS`. ([#1220](https://github.com/craftcms/commerce/issues/1220))
- Added `craft\commerce\services\Customers::EVENT_BEFORE_SAVE_CUSTOMER`.
- Added `craft\commerce\services\Webhooks::EVENT_AFTER_PROCESS_WEBHOOK`. ([#1799](https://github.com/craftcms/commerce/issues/1799))
- Added `craft\commerce\services\Webhooks::EVENT_BEFORE_PROCESS_WEBHOOK`. ([#1799](https://github.com/craftcms/commerce/issues/1799))

### Changed
- `salePrice` is now included when calling a purchasable’s `toArray()` method. ([#1793](https://github.com/craftcms/commerce/issues/1793))

### Deprecated
- Deprecated support for passing a `cartUpdatedNotice` param to the `commerce/cart/update-cart` action. A hashed `successMessage` param should be passed instead.

### Fixed
- Fixed a bug where changing the customer of an order could result in an “Address does not belong to customer” error. ([#1227](https://github.com/craftcms/commerce/issues/1227))
- Fixed a bug where cached discounts were not getting updated when a discount was saved or deleted. ([#1813](https://github.com/craftcms/commerce/pull/1813))
- Fixed formatting of URLs in the example templates. ([#1808](https://github.com/craftcms/commerce/issues/1808))
- Fixed a bug where `commerce/products/save-product`, `commerce/products/duplicate-product` and `commerce/products/delete-product` actions required the “Access Craft Commerce” permission. ([#1814](https://github.com/craftcms/commerce/pull/1814))
- Fixed a bug where it was possible to delete the default tax category.
- Fixed a bug where it was possible to delete the default shipping category.
- Restored the missing `craft\commerce\services\Payments::EVENT_AFTER_PROCESS_PAYMENT` event. ([#1818](https://github.com/craftcms/commerce/pull/1818))

## 3.2.8.1 - 2020-10-15

### Fixed
- Fixed a PHP error that occurred when duplicating a product. ([#1783](https://github.com/craftcms/commerce/issues/1783))

## 3.2.8 - 2020-10-13

### Added
- Added a “Variants” column to the Products index page. ([#1765](https://github.com/craftcms/commerce/issues/1765))
- Added the `commerce/reset-data` command. ([#581](https://github.com/craftcms/commerce/issues/581))
- Added `craft\commerce\console\controlllers\ResetData`.
- Added `craft\commerce\elements\Variants::getSkuAsText()`.
- Added `craft\commerce\helpers\Purchasable`.
- Added `craft\commerce\services\PaymentSources::getAllPaymentSourcesByGatewayId()`.

### Changed
- Coupon codes are no longer case-sensitive. ([#1763](https://github.com/craftcms/commerce/issues/1763))
- Disabled the browser autosuggest list when searching for a customer on the Edit Order page. ([#1752](https://github.com/craftcms/commerce/issues/1752))

### Fixed
- Fixed a PHP error that occurred when an order’s history was changed via a queue job.
- Fixed a bug where the store location address had its `isStoreLocation` property set to `false`. ([#1773](https://github.com/craftcms/commerce/issues/1773))
- Fixed a bug where the Template setting for product types wasn’t showing autosuggestions.
- Fixed a bug where disabled variants weren’t getting deleted along with their products. ([#1772](https://github.com/craftcms/commerce/issues/1772))
- Fixed a bug where incomplete carts weren’t getting updated when their selected payment gateway was disabled. ([#1531](https://github.com/craftcms/commerce/issues/1531))
- Fixed a bug where the incorrect confirmation message was shown when deleting a subscription plan.
- Fixed a PHP error that occurred when a subscription plan and gateway had been deleted. ([#1667](https://github.com/craftcms/commerce/issues/1667))
- Fixed a bug where address changes weren’t persisting on the Edit Order page. ([#1766](https://github.com/craftcms/commerce/issues/1766))
- Fixed a SQL error that could occur when saving a product, if it was disabled and missing required field values. ([#1764](https://github.com/craftcms/commerce/issues/1764))
- Fixed a bug where it was possible to change the primary currency after completed orders had been placed. ([#1777](https://github.com/craftcms/commerce/issues/1777))
- Fixed a JavaScript error that occurred on the payment page of the example templates.

## 3.2.7 - 2020-09-24

### Added
- Craft Commerce is now translated into Japanese.
- Added the ability to retrieve a customer’s addresses via Ajax. ([#1682](https://github.com/craftcms/commerce/issues/1682))
- Added the ability to retrieve a customer’s previous orders via Ajax. ([#1682](https://github.com/craftcms/commerce/issues/1682))
- Added `craft\commerce\controllers\CustomerAddressesController::actionGetAddresses`. ([#1682](https://github.com/craftcms/commerce/issues/1682))
- Added `craft\commerce\controllers\CustomerOrdersControllers`. ([#1682](https://github.com/craftcms/commerce/issues/1682))

### Changed
- Improved the performance of exporting orders using the “Raw Data” export type. ([#1744](https://github.com/craftcms/commerce/issues/1744))
- Disabled the browser autosuggest list when searching for a customer on the Edit Order page. ([#1752](https://github.com/craftcms/commerce/issues/1752))
- `craft\models\Customer::getOrders()` now returns orders sorted by date ordered, in descending order.

### Fixed
- Fixed a migration error that could occur when updating to Commerce 3. ([#1726](https://github.com/craftcms/commerce/issues/1726))
- Fixed a bug where control panel styles were getting included in rendered email previews. ([#1734](https://github.com/craftcms/commerce/issues/1734))
- Fixed a PHP error that could occur when saving an order without a customer ID.
- Fixed a PHP error that occurred when rendering a PDF, if the temp directory was missing. ([#1745](https://github.com/craftcms/commerce/issues/1745))
- Fixed a bug where `craft\commerce\models\Address:getAddressLines()` wasn’t including `countryText` in the response data.
- Fixed a PHP error that occurred when entering a localized number as a category shipping rule price.
- Fixed a bug where long translations would break the line item layout on the Edit Order page.
- Fixed a JavaScript error that occurred when editing shipping rules.

## 3.2.6 - 2020-09-13

### Fixed
- Fixed a bug that prevented a customer from unsubscribing from a subscription. ([#1650](https://github.com/craftcms/commerce/issues/1650))
- Fixed a bug that prevented a customer from deleting a payment source. ([#1650](https://github.com/craftcms/commerce/issues/1650))

## 3.2.5 - 2020-09-11

### Changed
- Purchasable descriptions are now generated based on data from the primary site only.
- JSON responses from the `commerce/payments/pay` action now include order information.

### Fixed
- Fixed a PHP error that occurred when exporting orders using the “Raw data” export type.
- Fixed a PHP error that could occur when resaving products. ([#1698](https://github.com/craftcms/commerce/issues/1698))
- Fixed a PHP error that occurred when using the `|commerceCurrency` Twig filter for currency conversion. ([#1702](https://github.com/craftcms/commerce/issues/1702))
- Fixed a SQL error that occurred when previewing emails on PostgreSQL. ([#1673](https://github.com/craftcms/commerce/issues/1673))
- Fixed a PHP error that occurred when there was a syntax error in an order condition formula. ([#1716](https://github.com/craftcms/commerce/issues/1716))
- Fixed a bug where order history records created at the same time were ordered incorrectly.
- Fixed a 400 error that could occur when a product type’s Template setting was blank.
- Fixed a bug where purchasables without a product type were incorrectly showing on the “Top Product Types” Dashboard widget. ([#1720](https://github.com/craftcms/commerce/issues/1720))
- Fixed a bug where shipping zone caches weren’t getting invalidated. ([#1721](https://github.com/craftcms/commerce/issues/1721))
- Fixed a Twig error that occurred when viewing the “Buy” example templates. ([#1722](https://github.com/craftcms/commerce/issues/1722))

## 3.2.4 - 2020-09-07

### Added
- Added the “Item Total” and “Item Subtotal” Orders index page columns. ([#1695](https://github.com/craftcms/commerce/issues/1695))
- Added the `hasProduct` argument to GraphQL variant queries. ([#1697](https://github.com/craftcms/commerce/issues/1697))
- Added `craft\commerce\elements\Order::$storedItemSubtotalAsCurrency`. ([#1695](https://github.com/craftcms/commerce/issues/1695))
- Added `craft\commerce\elements\Order::$storedItemSubtotal`. ([#1695](https://github.com/craftcms/commerce/issues/1695))
- Added `craft\commerce\services\Addresses::EVENT_BEFORE_DELETE_ADDRESS`. ([#1590](https://github.com/craftcms/commerce/pull/1590))

### Changed
- Address forms now show the Country field before State to avoid confusion when editing an address.
- Products’, subscriptions’, and orders’ date sort options are now sorted in descending order by default when selected (requires Craft 3.5.9 or later).
- `craft\commerce\models\Address::getAddressLines()` now has a `$sanitize` argument. ([#1671](https://github.com/craftcms/commerce/issues/1671))

### Deprecated
- Deprecated `craft\commerce\Plugin::t()`.
- Deprecated `craft\commerce\services\Discounts::populateDiscountRelations()`.

### Fixed
- Fixed a bug where donation queries weren’t returning complete results if the primary site had changed.
- Fixed a bug where addresses would always get a new ID when updating the cart. ([#1683](https://github.com/craftcms/commerce/issues/1683))
- Fixed a bug where sales weren’t being applied to orders on the Edit Order page. ([#1662](https://github.com/craftcms/commerce/issues/1662))
- Fixed a bug where users without orders weren’t available for selection in customer lists.
- Fixed a bug where the `*AsCurrency` order attributes were showing the base currency rather than the order currency. ([#1668](https://github.com/craftcms/commerce/issues/1668))
- Fixed a bug where it wasn’t possible to permanently delete orders from the Orders index page. ([#1708](https://github.com/craftcms/commerce/issues/1708))
- Fixed a bug where it wasn’t possible to permanently delete products from the Product index page. ([#1708](https://github.com/craftcms/commerce/issues/1708))
- Fixed a missing validation error when saving a product type. ([#1678](https://github.com/craftcms/commerce/issues/1678))
- Fixed a bug where address lines were getting double-encoded. ([#1671](https://github.com/craftcms/commerce/issues/1671))
- Fixed a bug where shipping method caches weren’t getting invalidated. ([#1674](https://github.com/craftcms/commerce/issues/1674))
- Fixed a bug where `dateUpdated` wasn’t getting populated when saving a line item. ([#1691](https://github.com/craftcms/commerce/issues/1691))
- Fixed a bug where purchasable descriptions were able to be longer than line item descriptions.
- Fixed a bug where discounts could be applied to products that were already on sale, even if their “Exclude this discount for products that are already on sale” condition was enabled. ([#1700](https://github.com/craftcms/commerce/issues/1700))
- Fixed a bug where discount condition formulas weren’t preventing discount codes from applying to a cart. ([#1705](https://github.com/craftcms/commerce/pull/1705))
- Fixed a bug where orders’ cached transactions were not getting updated when a transaction was saved. ([#1703](https://github.com/craftcms/commerce/pull/1703))

## 3.2.3 - 2020-08-19

### Fixed
- Fixed a SQL error that occurred when searching for orders from the Orders index page. ([#1652](https://github.com/craftcms/commerce/issues/1652))
- Fixed a bug where discounts with purchasable conditions were not being applied correctly to the cart. ([#1641](https://github.com/craftcms/commerce/issues/1641))
- Fixed a migration error that could occur when updating to Commerce 3.2. ([#1655](https://github.com/craftcms/commerce/issues/1655))
- Fixed a PHP error that occurred when displaying the “Top Product Types” Dashboard widget.
- Fixed a deprecation warning that occurred on the Orders index page. ([#1656](https://github.com/craftcms/commerce/issues/1656))
- Fixed a bug where Live Preview wasn’t showing custom fields for products. ([#1651](https://github.com/craftcms/commerce/issues/1651))

## 3.2.2.1 - 2020-08-14

### Fixed
- Fixed a bug where `craft\commerce\services\LineItemStatuses::getLineItemStatusByHandle()`, `getDefaultLineItemStatus()`, `getDefaultLineItemStatusForLineItem()` and `getLineItemStatusById()` were returning archived statuses. ([#1643](https://github.com/craftcms/commerce/issues/1643))
- Fixed a bug where line item status caches weren’t getting invalidated.

## 3.2.2 - 2020-08-13

### Added
- Added `craft\commerce\models\LineItem::setLineItemStatus()`. ([#1638](https://github.com/craftcms/commerce/issues/1638))
- Added `craft\commerce\services\LineItems::orderCompleteHandler()`.

### Changed
- Commerce now requires Craft 3.5.4 or later.

### Fixed
- Fixed a bug where the default line item status was not getting applied on order completion. ([#1643](https://github.com/craftcms/commerce/issues/1643))
- Fixed a bug where sales weren’t getting initialized with their `sortOrder` value. ([#1633](https://github.com/craftcms/commerce/issues/1633))
- Fixed a PHP error that could occur when downloading a PDF. ([#1626](https://github.com/craftcms/commerce/issues/1626))
- Fixed a PHP error that could occur when adding a custom purchasable to an order from the Edit Order page. ([#1646](https://github.com/craftcms/commerce/issues/1646))
- Fixed a bug where duplicate orders could be returned by an order query when using the `hasPurchasables` or `hasTransactions` params. ([#1637](https://github.com/craftcms/commerce/issues/1637))
- Fixed a bug where the Top Products and Top Product Types lists weren’t counting correctly on multi-site installs. ([#1644](https://github.com/craftcms/commerce/issues/1644))
- Fixed a bug where the Edit Order page wasn’t swapping the selected tab correctly when saving changes, if a custom tab was selected. ([#1647](https://github.com/craftcms/commerce/issues/1647))
- Fixed a bug where custom field JavaScript wasn’t getting initialized properly on the Edit Order page in some cases. ([#1647](https://github.com/craftcms/commerce/issues/1647))

## 3.2.1 - 2020-08-06

### Added
- Added `craft\commerce\models\Address::sameAs()`. ([#1616](https://github.com/craftcms/commerce/issues/1616))

### Fixed
- Fixed an error that could occur when rebuilding the project config. ([#1618](https://github.com/craftcms/commerce/issues/1618))
- Fixed an error that could occur on the order index page when viewing orders with deleted gateways. ([#1617](https://github.com/craftcms/commerce/issues/1617))
- Fixed a deprecation warning that occurred when sending an Ajax request to `commerce/cart/*` actions. ([#1548](https://github.com/craftcms/commerce/issues/1548))
- Fixed a bug where purchasables weren’t getting pre-populated properly when clicking “Add all to Sale” on the Edit Product page. ([#1595](https://github.com/craftcms/commerce/issues/1595))
- Fixed PHP 7.0 compatibility.
- Fixed a Twig error that occurred when viewing the “Buy” example templates. ([#1621](https://github.com/craftcms/commerce/issues/1621))

## 3.2.0.2 - 2020-08-04

### Fixed
- Fixed a bug that caused the product Title field to be hidden on Edit Product pages. ([#1614](https://github.com/craftcms/commerce/pull/1614))

## 3.2.0.1 - 2020-08-04

### Fixed
- Fixed a SQL error that could occur when updating to Commerce 3.2.

## 3.2.0 - 2020-08-04

### Added
- Order, product, and variant field layouts now support the new field layout features added in Craft 3.5.
- It’s now possible to set Title fields’ positions within product and variant field layouts.
- It’s now possible to set the Variants field’s position within product field layouts.
- Added support for managing multiple PDF templates. Each email can choose which PDF should be attached. ([#208](https://github.com/craftcms/commerce/issues/208))
- Added a “Download PDF” action to the Orders index page, which supports downloading multiple orders’ PDFs at once. ([#1598](https://github.com/craftcms/commerce/issues/1598))
- Shipping rules can now be duplicated from the Edit Shipping Rule page. ([#153](https://github.com/craftcms/commerce/issues/153))
- Added the ability to preview HTML emails from the Emails index page. ([#1387](https://github.com/craftcms/commerce/issues/1387))
- Variants now have a `product` field when queried via the GraphQL API.
- It’s now possible to query for variants by their dimensions. ([#1570](https://github.com/craftcms/commerce/issues/1570))
- Products can now have auto-generated titles with the “Title Format” product type setting. ([#148](https://github.com/craftcms/commerce/issues/148))
- Added the `withLineItems`, `withTransactions`, `withAdjustments`, `withCustomer` and `withAddresses` order query params, for eager-loading related models. ([#1603](https://github.com/craftcms/commerce/issues/1603))
- Added `apply`, `applyAmount`, `applyAmountAsPercent`, `applyAmountAsFlat`, `dateFrom` and `dateTo` fields to sales when queried via the GraphQL API. ([#1607](https://github.com/craftcms/commerce/issues/1607))
- Added the `freeOrderPaymentStrategy` config setting. ([#1526](https://github.com/craftcms/commerce/pull/1526))
- Added the `cp.commerce.order.edit.details` template hook. ([#1597](https://github.com/craftcms/commerce/issues/1597))
- Added `craft\commerce\controllers\Pdf`.
- Added `craft\commerce\elements\Orders::EVENT_AFTER_APPLY_ADD_LINE_ITEM`. ([#1516](https://github.com/craftcms/commerce/pull/1516))
- Added `craft\commerce\elements\Orders::EVENT_AFTER_APPLY_REMOVE_LINE_ITEM`. ([#1516](https://github.com/craftcms/commerce/pull/1516))
- Added `craft\commerce\elements\db\VariantQuery::width()`, `height()`, `length()` and `weight()`. ([#1570](https://github.com/craftcms/commerce/issues/1570))
- Added `craft\commerce\events\DefineAddressLinesEvent`.
- Added `craft\commerce\fieldlayoutelements\ProductTitleField`.
- Added `craft\commerce\fieldlayoutelements\VariantTitleField`.
- Added `craft\commerce\fieldlayoutelements\VariantsField`.
- Added `craft\commerce\models\Address::getAddressLines()`.
- Added `craft\commerce\models\EVENT_DEFINE_ADDRESS_LINES`. ([#1305](https://github.com/craftcms/commerce/issues/1305))
- Added `craft\commerce\models\Email::$pdfId`.
- Added `craft\commerce\models\LineItem::dateUpdated`. ([#1132](https://github.com/craftcms/commerce/issues/1132))
- Added `craft\commerce\models\Pdf`.
- Added `craft\commerce\records\Pdf`.
- Added `craft\commerce\services\Addresses::eagerLoadAddressesForOrders()`.
- Added `craft\commerce\services\Customers::eagerLoadCustomerForOrders()`.
- Added `craft\commerce\services\LineItems::eagerLoadLineItemsForOrders()`.
- Added `craft\commerce\services\OrderAdjustments::eagerLoadOrderAdjustmentsForOrders()`.
- Added `craft\commerce\services\Pdfs::EVENT_AFTER_SAVE_PDF`.
- Added `craft\commerce\services\Pdfs::EVENT_BEFORE_SAVE_PDF`.
- Added `craft\commerce\services\Pdfs::getAllEnabledPdfs()`.
- Added `craft\commerce\services\Pdfs::getAllPdfs()`.
- Added `craft\commerce\services\Pdfs::getDefaultPdf()`.
- Added `craft\commerce\services\Pdfs::getPdfByHandle()`.
- Added `craft\commerce\services\Pdfs::getPdfById()`.
- Added `craft\commerce\services\Pdfs::savePdf()`.
- Added `craft\commerce\services\Transactions::eagerLoadTransactionsForOrders()`.

### Changed
- Commerce now requires Craft 3.5.0 or later.
- Improved the performance of order indexes.
- Improved the performance of querying for products and orders via the GraphQL API.
- Countries are now initially sorted by name, rather than country code.
- Improved customer search and creation when editing an order. ([#1594](https://github.com/craftcms/commerce/issues/1594))
- It’s now possible to use multiple keywords when searching for variants from the Edit Order page. ([#1546](https://github.com/craftcms/commerce/pull/1546))
- New products, countries, states, and emails are now enabled by default.

### Deprecated
- Deprecated `craft\commerce\controllers\Orders::actionPurchasableSearch()`. Use `actionPurchasablesTable()` instead.
- Deprecated `craft\commerce\services\Sales::populateSaleRelations()`.
- Deprecated the `orderPdfPath` config setting.
- Deprecated the `orderPdfFilenameFormat` config setting.

### Removed
- Removed `craft\commerce\models\ProductType::$titleLabel`.
- Removed `craft\commerce\models\ProductType::$variantTitleLabel`.
- Removed `craft\commerce\records\ProductType::$titleLabel`.
- Removed `craft\commerce\records\ProductType::$variantTitleLabel`.
- Removed `craft\commerce\models\Email::$pdfTemplatePath`.
- Removed `craft\commerce\records\Email::$pdfTemplatePath`.

### Fixed
- Fixed a bug where interactive custom fields weren’t working within newly created product variants, from product editor HUDs.
- Fixed a bug where it was possible to select purchasables that weren’t available for purchase on the Edit Order page. ([#1505](https://github.com/craftcms/commerce/issues/1505))
- Fixed a PHP error that could occur during line item validation on Yii 2.0.36. ([yiisoft/yii2#18175](https://github.com/yiisoft/yii2/issues/18175))
- Fixed a bug that prevented shipping rules for being sorted correctly on the Edit Shipping Method page.
- Fixed a bug where programmatically-set related IDs could be ignored when saving a sale.
- Fixed a bug where order status descriptions were getting dropped when rebuilding the project config.

## 3.1.12 - 2020-07-14

### Changed
- Improved the wording of the “Categories Relationship Type” setting’s instructions and option labels on Edit Sale and Edit Discount pages. ([#1565](https://github.com/craftcms/commerce/pull/1565))

### Fixed
- Fixed a bug where existing sales and discounts would get the wrong “Categories Relationship Type” seletion when upgrading to Commerce 3. ([#1565](https://github.com/craftcms/commerce/pull/1565))
- Fixed a bug where the wrong shipping method could be selected for completed orders on the Edit Order page. ([#1557](https://github.com/craftcms/commerce/issues/1557))
- Fixed a bug where it wasn’t possible to update a customer’s primary billing or shipping address from the front end. ([#1562](https://github.com/craftcms/commerce/issues/1562))
- Fixed a bug where customers’ states weren’t always shown in the control panel. ([#1556](https://github.com/craftcms/commerce/issues/1556))
- Fixed a bug where programmatically removing an unsaved line item could remove the wrong line item. ([#1555](https://github.com/craftcms/commerce/issues/1555))
- Fixed a PHP error that could occur when using the `currency` Twig filter. ([#1554](https://github.com/craftcms/commerce/issues/1554))
- Fixed a PHP error that could occur on the order completion template when outputting dates. ([#1030](https://github.com/craftcms/commerce/issues/1030))
- Fixed a bug that could occur if a gateway had truncated its “Gateway Message”.

## 3.1.11 - 2020-07-06

### Added
- Added new `*AsCurrency` attributes to all currency attributes on orders, line items, products, variants, adjustments and transactions.
- Added the `hasVariant` argument to GraphQL product queries. ([#1544](https://github.com/craftcms/commerce/issues/1544))
- Added `craft\commerce\events\ModifyCartInfoEvent::$cart`. ([#1536](https://github.com/craftcms/commerce/issues/1536))
- Added `craft\commerce\behaviors\CurrencyAttributeBehavior`.
- Added `craft\commerce\gql\types\input\Variant`.

### Fixed
- Improved performance when adding items to the cart. ([#1543](https://github.com/craftcms/commerce/pull/1543), [#1520](https://github.com/craftcms/commerce/issues/1520))
- Fixed a bug where products that didn’t have current sales could be returned when the `hasSales` query parameter was enabled.
- Fixed a bug where the “Message” field wasn’t getting cleared after updating the order status on the Order edit page. ([#1366](https://github.com/craftcms/commerce/issues/1366))
- Fixed a bug where it wasn’t possible to update the conversion rate on a payment currency. ([#1547](https://github.com/craftcms/commerce/issues/1547))
- Fixed a bug where it wasn’t possible to delete all line item statuses.
- Fixed a bug where zero currency values weren’t getting formatted correctly in `commerce/cart/*` actions’ JSON responses. ([#1539](https://github.com/craftcms/commerce/issues/1539))
- Fixed a bug where the wrong line item could be added to the cart when using the Lite edition. ([#1552](https://github.com/craftcms/commerce/issues/1552))
- Fixed a bug where a validation error was being shown incorrectly on the Edit Discount page. ([#1549](https://github.com/craftcms/commerce/issues/1549))

### Deprecated
- The `|json_encode_filtered` twig filter has now been deprecated.

## 3.1.10 - 2020-06-23

### Added
- Added the `salePrice` and `sales` fields to GraphQL variant queries. ([#1525](https://github.com/craftcms/commerce/issues/1525))
- Added support for non-parameterized gateway webhook URLs. ([#1530](https://github.com/craftcms/commerce/issues/1530))
- Added `craft\commerce\gql\types\SaleType`.

### Changed
- The selected shipping method now shows both name and handle for completed orders on the Edit Order page. ([#1472](https://github.com/craftcms/commerce/issues/1472))

### Fixed
- Fixed a bug where the current user’s email was unintentionally being used as a fallback when creating a customer with an invalid email address on the Edit Order page. ([#1523](https://github.com/craftcms/commerce/issues/1523))
- Fixed a bug where an incorrect validation error would be shown when using custom address validation on the Edit Order page. ([#1519](https://github.com/craftcms/commerce/issues/1519))
- Fixed a bug where `defaultVariantId` wasn’t being set when saving a Product. ([#1529](https://github.com/craftcms/commerce/issues/1529))
- Fixed a bug where custom shipping methods would show a zero price. ([#1532](https://github.com/craftcms/commerce/issues/1532))
- Fixed a bug where the payment form modal wasn’t getting sized correctly on the Edit Order page. ([#1441](https://github.com/craftcms/commerce/issues/1441))
- Fixed the link to Commerce documentation from the control panel. ([#1517](https://github.com/craftcms/commerce/issues/1517))
- Fixed a deprecation warning for `Order::getAvailableShippingMethods()` on the Edit Order page. ([#1518](https://github.com/craftcms/commerce/issues/1518))

## 3.1.9 - 2020-06-17

### Added
- Added `craft\commerce\base\Gateway::getTransactionHashFromWebhook()`.
- Added `craft\commerce\services\OrderAdjustments::EVENT_REGISTER_DISCOUNT_ADJUSTERS`.
- Added `craft\commerce\services\Webhooks`.

### Changed
- Discount calculations now take adjustments created by custom discount adjusters into account. ([#1506](https://github.com/craftcms/commerce/issues/1506))
- Improved handling of race conditions between processing a webhook and completing an order. ([#1510](https://github.com/craftcms/commerce/issues/1510))
- Improved performance when retrieving order statuses. ([#1497](https://github.com/craftcms/commerce/issues/1497))

### Fixed
- Fixed a bug where zero stock items would be removed from the order before accepting payment. ([#1503](https://github.com/craftcms/commerce/issues/1503))
- Fixed an error that occurred when saving an order with a deleted variant on the Edit Order page. ([#1504](https://github.com/craftcms/commerce/issues/1504))
- Fixed a bug where line items weren’t being returned in the correct order after adding a new line item to the card via Ajax. ([#1496](https://github.com/craftcms/commerce/issues/1496))
- Fixed a bug where countries and states weren’t being returned in the correct order. ([#1512](https://github.com/craftcms/commerce/issues/1512))
- Fixed a deprecation warning. ([#1508](https://github.com/craftcms/commerce/issues/1508))

## 3.1.8 - 2020-06-11

### Added
- Added `craft\commerce\services\Sales::EVENT_AFTER_DELETE_SALE`.

### Changed
- Custom adjuster types now show as read-only on the Edit Order page. ([#1460](https://github.com/craftcms/commerce/issues/1460))
- Variant SKU, price, and stock validation is now more lenient unless the product and variant are enabled.

### Fixed
- Fixed a bug where empty carts would get new cart numbers on every request. ([#1486](https://github.com/craftcms/commerce/issues/1486))
- Fixed a PHP error that occurred when saving a payment source using an erroneous card. ([#1492](https://github.com/craftcms/commerce/issues/1492))
- Fixed a bug where deleted orders were being included in reporting widget calculations. ([#1490](https://github.com/craftcms/commerce/issues/1490))
- Fixed the styling of line item option values on the Edit Order page.
- Fixed a SQL error that occurred when duplicating a product on a multi-site Craft install. ([#1491](https://github.com/craftcms/commerce/issues/1491))
- Fixed a bug where products could be duplicated even if there was a validation error that made it look like the product hadn’t been duplicated.

## 3.1.7 - 2020-06-02

### Fixed
- Fixed a bug where blank addresses were being automatically created on new carts. ([#1486](https://github.com/craftcms/commerce/issues/1486))
- Fixed a SQL error that could occur during order consolidation on PostgreSQL.

## 3.1.6 - 2020-06-02

### Changed
- `craft\commerce\services\Customers::consolidateOrdersToUser()` is no longer deprecated.

### Fixed
- Fixed a bug where the “Purchase Total” and “Purchase Quantity” discount conditions weren’t being applied correctly. ([#1389](https://github.com/craftcms/commerce/issues/1389))
- Fixed a bug where a customer could be deleted if `Order::$registerUserOnOrderComplete` was set to `true` on order completion. ([#1483](https://github.com/craftcms/commerce/issues/1483))
- Fixed a bug where it wasn’t possible to save an order without addresses on the Edit Order page. ([#1484](https://github.com/craftcms/commerce/issues/1484))
- Fixed a bug where addresses weren’t being set automatically when retrieving a cart. ([#1476](https://github.com/craftcms/commerce/issues/1476))
- Fixed a bug where transaction information wasn’t being displayed correctly on the Edit Order page. ([#1467](https://github.com/craftcms/commerce/issues/1467))
- Fixed a bug where `commerce/pay/*` and `commerce/customer-addresses/*` actions ignored the `updateCartSearchIndexes` config setting.
- Fixed a deprecation warning. ([#1481](https://github.com/craftcms/commerce/issues/1481))

## 3.1.5 - 2020-05-27

### Added
- Added the `updateCartSearchIndexes` config setting. ([#1416](https://github.com/craftcms/commerce/issues/1416))
- Added `craft\commerce\services\Discounts::EVENT_DISCOUNT_MATCHES_ORDER`.
- Renamed the `Totals` column to `All Totals` and `Total` to `Total Price` on the Orders index page. ([#1482](https://github.com/craftcms/commerce/issues/1482))

### Deprecated
- Deprecated `craft\commerce\services\Discounts::EVENT_BEFORE_MATCH_LINE_ITEM`. `EVENT_DISCOUNT_MATCHES_LINE_ITEM` should be used instead.

### Fixed
- Fixed a PHP error that could occur on Craft 3.5. ([#1471](https://github.com/craftcms/commerce/issues/1471))
- Fixed a bug where the “Purchase Total” discount condition would show a negative value.
- Fixed a bug where payment transaction amounts where not being formatted correctly on Edit Order pages. ([#1463](https://github.com/craftcms/commerce/issues/1463))
- Fixed a bug where free shipping discounts could be applied incorrectly. ([#1473](https://github.com/craftcms/commerce/issues/1473))

## 3.1.4 - 2020-05-18

### Added
- Added a “Duplicate” action to the Products index page. ([#1107](https://github.com/craftcms/commerce/issues/1107))
- It’s now possible to query for a single product or variant via GraphQL.
- Address and line item notes now support emoji characters. ([#1426](https://github.com/craftcms/commerce/issues/1426))
- Added `craft\commerce\fields\Products::getContentGqlType()`.
- Added `craft\commerce\fields\Variants::getContentGqlType()`.
- Added `craft\commerce\models\Address::getCountryIso()`. ([#1419](https://github.com/craftcms/commerce/issues/1419))
- Added `craft\commerce\web\assets\commerceui\CommerceOrderAsset`.

### Changed
- It’s now possible to add multiple line items at a time on the Edit Order page. ([#1446](https://github.com/craftcms/commerce/issues/1446))
- It’s now possible to copy the billing address over to the shipping address, and vise-versa, on Edit Order pages. ([#1412](https://github.com/craftcms/commerce/issues/1412))
- Edit Order pages now link to the customer’s edit page. ([#1397](https://github.com/craftcms/commerce/issues/1397))
- Improved the line item options layout on the Edit Order page.

### Fixed
- Fixed a bug where products weren’t getting duplicate correctly when the “Save as a new product” option was selected. ([#1393](https://github.com/craftcms/commerce/issues/1393))
- Fixed a bug where addresses were being incorrectly duplicated when updating a cart from the Edit Order page. ([#1435](https://github.com/craftcms/commerce/issues/1435))
- Fixed a bug where `product` and `variant` fields were returning the wrong type in GraphQL queries. ([#1434](https://github.com/craftcms/commerce/issues/1434))
- Fixed a SQL error that could occur when saving a product. ([#1407](https://github.com/craftcms/commerce/pull/1407))
- Fixed a bug where only admin users were allowed to add line item on the Edit Order page. ([#1424](https://github.com/craftcms/commerce/issues/1424))
- Fixed a bug where it wasn’t possible to remove an address on the Edit Order page. ([#1436](https://github.com/craftcms/commerce/issues/1436))
- Fixed a bug where user groups would be unset when saving a primary address on the Edit User page. ([#1421](https://github.com/craftcms/commerce/issues/1421))
- Fixed a PHP error that could occur when saving an address. ([#1417](https://github.com/craftcms/commerce/issues/1417))
- Fixed a bug where entering a localized number for a base discount value would save incorrectly. ([#1400](https://github.com/craftcms/commerce/issues/1400))
- Fixed a bug where blank addresses were being set on orders from the Edit Order page. ([#1401](https://github.com/craftcms/commerce/issues/1401))
- Fixed a bug where past orders weren’t being consolidated for new users. ([#1423](https://github.com/craftcms/commerce/issues/1423))
- Fixed a bug where unnecessary order recalculation could occur during a payment request. ([#1431](https://github.com/craftcms/commerce/issues/1431))
- Fixed a bug where variants weren’t getting resaved automatically if their field layout was removed from the product type settings. ([#1359](https://github.com/craftcms/commerce/issues/1359))
- Fixed a PHP error that could occur when saving a discount.

## 3.1.3 - 2020-04-22

### Fixed
- Fixed a PHP error that occurred when saving variants. ([#1403](https://github.com/craftcms/commerce/pull/1403))
- Fixed an error that could occur when processing Project Config changes that also included new sites. ([#1390](https://github.com/craftcms/commerce/issues/1390))
- Fixed a bug where “Purchase Total” and “Purchase Quantity” discount conditions weren’t being applied correctly. ([#1389](https://github.com/craftcms/commerce/issues/1389))

## 3.1.2 - 2020-04-17

### Added
- It’s now possible to query for products and variants by their custom field values via GraphQL.
- Added the `variants` field to GraphQL product queries.
- Added `craft\commerce\service\Variants::getVariantGqlContentArguments()`.

### Changed
- It’s now possible to query for orders using multiple email addresses. ([#1361](https://github.com/craftcms/commerce/issues/1361))
- `craft\commerce\controllers\CartController::$_cart` is now protected.
- `craft\commerce\controllers\CartController::$_cartVariable` is now protected.

### Deprecated
- Deprecated `craft\commerce\queue\jobs\ConsolidateGuestOrders::consolidate()`. `craft\commerce\services\Customers::consolidateGuestOrdersByEmail()` should be used instead.

### Fixed
- Fixed a bug where orders weren’t marked as complete when using an offsite gateway and the “authorize” payment type.
- Fixed an error that occurred when attempting to pay for an order from the control panel. ([#1362](https://github.com/craftcms/commerce/issues/1362))
- Fixed a PHP error that occurred when using a custom shipping method during checkout. ([#1378](https://github.com/craftcms/commerce/issues/1378))
- Fixed a bug where Edit Address pages weren’t redirecting back to the Edit User page on save. ([#1368](https://github.com/craftcms/commerce/issues/1368))
- Fixed a bug where selecting the “All Orders” source on the Orders index page wouldn’t update the browser’s history. ([#1367](https://github.com/craftcms/commerce/issues/1367))
- Fixed a bug where the Orders index page wouldn’t work as expected after cancelling an order status update. ([#1375](https://github.com/craftcms/commerce/issues/1375))
- Fixed a bug where the Edit Order pages would continue showing the previous order status message after it had been changed. ([#1366](https://github.com/craftcms/commerce/issues/1366))
- Fixed a race condition that could occur when consolidating guest orders.
- Fixed a bug where the Edit Order page was showing order-level adjustments’ “Edit” links for incomplete orders. ([#1374](https://github.com/craftcms/commerce/issues/1374))
- Fixed a PHP error that could occur when viewing a disabled country in the control panel.
- Fixed a bug where `craft\commerce\models\LineItem::$saleAmount` was being incorrectly validated. ([#1365](https://github.com/craftcms/commerce/issues/1365))
- Fixed a bug where variants weren’t getting deleted when a product was hard-deleted. ([#1186](https://github.com/craftcms/commerce/issues/1186))
- Fixed a bug where the `cp.commerce.product.edit.details` template hook was getting called in the wrong place in Edit Product pages. ([#1376](https://github.com/craftcms/commerce/issues/1376))
- Fixed a bug where line items’ caches were not being invalidated on save. ([#1377](https://github.com/craftcms/commerce/issues/1377))

## 3.1.1 - 2020-04-03

### Changed
- Line items’ sale amounts are now calculated automatically.

### Fixed
- Fixed a bug where orders weren’t saving properly during payment.
- Fixed a bug where it wasn’t obvious how to set shipping and billing addresses on a new order. ([#1354](https://github.com/craftcms/commerce/issues/1354))
- Fixed a bug where variant blocks were getting extra padding above their fields.
- Fixed an error that could occur when using the `|commerceCurrency` Twig filter if the Intl extension wasn’t enabled. ([#1353](https://github.com/craftcms/commerce/issues/1353))
- Fixed a bug where the `hasSales` variant query param could override most other params.
- Fixed a SQL error that could occur when querying for variants using the `hasStock` param on PostgreSQL. ([#1356](https://github.com/craftcms/commerce/issues/1356))
- Fixed a SQL error that could occur when querying for orders using the `isPaid` or `isUnpaid` params on PostgreSQL.
- Fixed a bug where passing `false` to a subscription query’s `isCanceled` or `isExpired` params would do nothing.

## 3.1.0.1 - 2020-04-02

### Fixed
- Fixed a bug where the `commerce_discounts` table was missing an `orderConditionFormula` column on fresh installs. ([#1351](https://github.com/craftcms/commerce/issues/1351))

## 3.1.0 - 2020-04-02

### Added
- It’s now possible to set dynamic condition formulas on discounts. ([#470](https://github.com/craftcms/commerce/issues/470))
- It’s now possible to reorder states. ([#1284](https://github.com/craftcms/commerce/issues/1284))
- It’s now possible to load a previous cart into the current session. ([#1348](https://github.com/craftcms/commerce/issues/1348))
- Customers can now pay the outstanding balance on a cart or completed order.
- It’s now possible to pass a `paymentSourceId` param on `commerce/payments/pay` requests, to set the desired payment gateway at the time of payment. ([#1283](https://github.com/craftcms/commerce/issues/1283))
- Edit Order pages now automatically populate the billing and shipping addresses when a new customer is selected. ([#1295](https://github.com/craftcms/commerce/issues/1295))
- It’s now possible to populate the billing and shipping addresses on an order based on existing addresses in the customer’s address book. ([#990](https://github.com/craftcms/commerce/issues/990))
- JSON responses for `commerce/cart/*` actions now include an `availableShippingMethodOptions` array, which lists all available shipping method options and their prices.
- It’s now possible to query for variants via GraphQL. ([#1315](https://github.com/craftcms/commerce/issues/1315))
- It’s now possible to set an `availableForPurchase` argument when querying for products via GraphQL.
- It’s now possible to set a `defaultPrice` argument when querying for products via GraphQL.
- Products now have an `availableForPurchase` field when queried via GraphQL.
- Products now have a `defaultPrice` field when queried via GraphQL.
- Added `craft\commerce\adjusters\Tax::_getTaxAmount()`.
- Added `craft\commerce\base\TaxEngineInterface`.
- Added `craft\commerce\controllers\AddressesController::actionValidate()`.
- Added `craft\commerce\controllers\AddressesController::getAddressById()`.
- Added `craft\commerce\controllers\AddressesController::getCustomerAddress()`.
- Added `craft\commerce\controllers\CartController::actionLoadCart()`.
- Added `craft\commerce\elements\Order::getAvailableShippingMethodsOptions()`.
- Added `craft\commerce\elements\Order::removeBillingAddress()`.
- Added `craft\commerce\elements\Order::removeEstimateBillingAddress()`.
- Added `craft\commerce\elements\Order::removeEstimateShippingAddress()`.
- Added `craft\commerce\elements\Order::removeShippingAddress()`.
- Added `craft\commerce\elements\Variant::getGqlTypeName()`.
- Added `craft\commerce\elements\Variant::gqlScopesByContext()`.
- Added `craft\commerce\elements\Variant::gqlTypeNameByContext()`.
- Added `craft\commerce\engines\TaxEngine`.
- Added `craft\commerce\gql\arguments\elements\Variant`.
- Added `craft\commerce\gql\arguments\interfaces\Variant`.
- Added `craft\commerce\gql\arguments\queries\Variant`.
- Added `craft\commerce\gql\arguments\resolvers\Variant`.
- Added `craft\commerce\gql\arguments\types\elements\Variant`.
- Added `craft\commerce\gql\arguments\types\generators\VariantType`.
- Added `craft\commerce\models\Settings::$loadCartRedirectUrl`.
- Added `craft\commerce\models\ShippingMethodOption`.
- Added `craft\commerce\services\Addresses::removeReadOnlyAttributesFromArray()`.
- Added `craft\commerce\services\Carts::getCartName()`.
- Added `craft\commerce\services\Customers::getCustomersQuery()`.
- Added `craft\commerce\services\Taxes`.

### Changed
- Improved performance for installations with millions of orders.
- Improved the “Add a line item” behavior and styling on the Edit Order page.
- Discount adjustments are now only applied to line items, not the whole order. The “Base discount” amount is now spread across all line items.
- Line items’ sale prices are now rounded before being multiplied by the quantity.
- Improved the consistency of discount and tax calculations and rounding logic across the system.
- Products and subscriptions can now be sorted by their IDs in the control panel.
- Improved the styling and behavior of the example templates.

### Deprecated
- Deprecated the ability to create percentage-based order-level discounts.

### Fixed
- Fixed an error that could occur when querying for products by type via GraphQL.
- Fixed a bug where it was possible to issue refunds for more than the remaining transaction amount. ([#1098](https://github.com/craftcms/commerce/issues/1098))
- Fixed a bug where order queries could return orders in the wrong sequence when ordered by `dateUpdated`. ([#1345](https://github.com/craftcms/commerce/issues/1345))
- Fixed a PHP error that could occur on the Edit Order page if the customer had been deleted. ([#1347](https://github.com/craftcms/commerce/issues/1347))
- Fixed a bug where shipping rules and discounts weren’t properly supporting localized number formats. ([#1332](https://github.com/craftcms/commerce/issues/1332), [#1174](https://github.com/craftcms/commerce/issues/1174))
- Fixed an error that could occur while updating an order status message, if the order was being recalculated at the same time. ([#1309](https://github.com/craftcms/commerce/issues/1309))
- Fixed an error that could occur when deleting an address on the front end.

## 3.0.12 - 2020-03-20

### Added
- Added the `validateCartCustomFieldsOnSubmission` config setting. ([#1292](https://github.com/craftcms/commerce/issues/1292))
- It’s now possible to search orders by the SKUs being purchased. ([#1328](https://github.com/craftcms/commerce/issues/1328))
- Added `craft\commerce\services\Carts::restorePreviousCartForCurrentUser()`.

### Changed
- Updated the minimum required version to upgrade to `2.2.18`.

### Fixed
- Fixed a bug where “Purchase Total” and “Purchase Quantity” discount conditions were not checked when removing shipping costs. ([#1321](https://github.com/craftcms/commerce/issues/1321))
- Fixed an error that could occur when eager loading `product` on a variant query.
- Fixed an PHP error that could occur when all countries are disabled. ([#1314](https://github.com/craftcms/commerce/issues/1314))
- Fixed a bug that could occur for logged in users when removing all items from the cart. ([#1319](https://github.com/craftcms/commerce/issues/1319))

## 3.0.11 - 2020-02-25

### Added
- Added the `cp.commerce.subscriptions.edit.content`, `cp.commerce.subscriptions.edit.meta`, and `cp.commerce.product.edit.content` template hooks. ([#1290](https://github.com/craftcms/commerce/pull/1290))

### Changed
- The order index page now updates the per-status order counts after using the “Update Order Status” action. ([#1217](https://github.com/craftcms/commerce/issues/1217))

### Fixed
- Fixed an error that could occur when editing variants’ stock value. ([#1288](https://github.com/craftcms/commerce/issues/1288))
- Fixed a bug where `0` values were being shown for order amounts. ([#1293](https://github.com/craftcms/commerce/issues/1293))

## 3.0.10 - 2020-02-20

### Fixed
- Fixed an error that could occur when creating a new product.

## 3.0.9 - 2020-02-19

### Fixed
- Fixed a migration error that could occur when updating. ([#1285](https://github.com/craftcms/commerce/issues/1285))

## 3.0.8 - 2020-02-18

### Fixed
- Fixed an SQL error that could occur when updating to Commerce 3.

## 3.0.7 - 2020-02-18

### Added
- Order indexes can now have a “Totals” column.
- Added `craft\commerce\models\LineItem::$sku`.
- Added `craft\commerce\models\LineItem::$description`.
- Added `craft\commerce\elements\Order::$dateAuthorized`.
- Added `craft\commerce\elements\Order::EVENT_AFTER_ORDER_AUTHORIZED`.
- Added `craft\commerce\models\LineItem::$sku`.
- Added `craft\commerce\models\LineItem::$description`.

### Changed
- Line items now store their purchasable’s SKU and description directly, in addition to within the snapshot.
- Ajax requests to `commerce/cart/*` now include line items’ `subtotal` values in their responses. ([#1263](https://github.com/craftcms/commerce/issues/1263))

### Fixed
- Fixed a bug where `commerce/cart/*` actions weren’t formatting `0` values correctly in their JSON responses. ([#1278](https://github.com/craftcms/commerce/issues/1278))
- Fixed a bug that caused adjustments’ “Included” checkbox to be ticked when editing another part of the order. ([#1234](https://github.com/craftcms/commerce/issues/1243))
- Fixed a JavaScript error that could occur when editing products. ([#1273](https://github.com/craftcms/commerce/issues/1273))
- Restored the missing “New Subscription Plan” button. ([#1271](https://github.com/craftcms/commerce/pull/1271))
- Fixed an error that could occur when updating to Commerce 3 from 2.2.5 or earlier.
- Fixed a bug where the “Transactions” tab on Edit Order pages was disabled for incomplete orders. ([#1268](https://github.com/craftcms/commerce/issues/1268))
- Fixed a error that prevented redirection back to the Edit Customer page after editing an address.

## 3.0.6 - 2020-02-06

### Added
- It’s now possible to sort customers by email address.

### Fixed
- Fixed PHP 7.0 compatibility. ([#1262](https://github.com/craftcms/commerce/issues/1262))
- Fixed a bug where it wasn’t possible to refund orders. ([#1259](https://github.com/craftcms/commerce/issues/1259))
- Fixed a bug where it wasn’t possible to add purchasables to an order on the Edit Order page.
- Fixed a bug where clicking on “Save and return to all orders” wouldn’t redirect back to the Orders index page. ([#1266](https://github.com/craftcms/commerce/issues/1266))
- Fixed an error that occurred when attempting to open a product editor HUD.

## 3.0.5 - 2020-01-31

### Fixed
- Fixed a bug that prevented emails from being sent. ([#1257](https://github.com/craftcms/commerce/issues/1257))

## 3.0.4 - 2020-01-31

### Added
- Orphaned addresses are now purged as part of garbage collection.
- Added `craft\commerce\services\Addresses::purgeOrphanedAddresses()`.
- Added the `commerce/addresses/set-primary-address` action.

### Changed
- `craft\commerce\events\OrderStatusEvent` no longer extends `craft\events\CancelableEvent`. ([#1244](https://github.com/craftcms/commerce/issues/1244))

### Fixed
- Fixed an error that could occur when trying to changing the customer the Edit Order page. ([#1238](https://github.com/craftcms/commerce/issues/1238))
- Fixed a PHP error that occurred on Windows environments. ([#1247](https://github.com/craftcms/commerce/issues/1247))
- Fixed a bug where orders’ Date Ordered attributes could shift after saving an order from the Edit Order page. ([#1246](https://github.com/craftcms/commerce/issues/1246))
- Fixed a bug that caused the “Variant Fields” tab to disappear on Edit Product Type pages.
- Fixed a bug that prevented emails from being sent. ([#1257](https://github.com/craftcms/commerce/issues/1257))
- Fixed a error that occurred on the Edit User page when the logged-in user did’t have the “Manage subscriptions” permission. ([#1252](https://github.com/craftcms/commerce/issues/1252))
- Fixed an error that occurred when setting a primary address on a customer. ([#1253](https://github.com/craftcms/commerce/issues/1253))
- Fixed an error that could occur when selecting certain options on the Total Revenue dashboard widget. ([#1255](https://github.com/craftcms/commerce/issues/1255))
- Fixed an error that could occur when sending an email from the Edit Order page if the email settings had not be resaved after updating to Craft Commerce 3.
- Fixed a bug where it wasn’t possible to change order statuses and custom field values when using the Lite edition.
- Fixed an error that could occur on order complete if a discount had been applied programmatically.

## 3.0.3 - 2020-01-29

### Fixed
- Fixed the styling of the address’s “Edit” button on the Edit Order page.

## 3.0.2 - 2020-01-29

### Added
- Ajax requests to `commerce/cart/*` now include `totalTax`, `totalTaxIncluded`, `totalDiscount`, and `totalShippingCost` fields in the JSON response.

### Fixed
- Fixed a PostgreSQL error that occurred on the Edit Order page.

## 3.0.1 - 2020-01-29

### Changed
- A customer record is now created when saving a user. ([#1237](https://github.com/craftcms/commerce/issues/1237))

### Fixed
- Fixed an error that occurred on order complete. ([#1239](https://github.com/craftcms/commerce/issues/1239))

## 3.0.0 - 2020-01-28

> {warning} Order notification emails are now sent via a queue job, so running a queue worker as a daemon is highly recommended to avoid notification delays.

> {warning} Plugins and modules that modify the Edit Order page are likely to break with this update.

### Added
- Commerce 3.0 requires Craft 3.4 or later.
- Added the ability to create and edit orders from the control panel.
- Added the ability to manage customers and customer addresses from the control panel. ([#1043](https://github.com/craftcms/commerce/issues/1043))
- Added GraphQL support for products. ([#1092](https://github.com/craftcms/commerce/issues/1092))
- Added the ability to send emails from the Edit Order page.
- Line items can now be exported from the Orders index page. ([#976](https://github.com/craftcms/commerce/issues/976))
- Added the “Edit orders” and “Delete orders” user permissions.
- Line items now have a status that can be changed on Edit Order pages.
- Line items now have a Private Note field for store managers.
- Inactive carts are now purged during garbage collection.
- Orders now have recalculation modes to determine what should be recalculated on the order.
- Added the `origin` order query param.
- Added the `hasLineItems` order query param.
- `commerce/payments/pay` JSON responses now include an `orderErrors` array if there were any errors on the order.
- Added warnings to settings that are being overridden in the config file. ([#746](https://github.com/craftcms/commerce/issues/746))
- Promotions can now specify which elements are the source vs. target on category relations added by the promotion. ([#984](https://github.com/craftcms/commerce/issues/984))
- Added the ability to add products existing sales from Edit Product pages. ([#594](https://github.com/craftcms/commerce/issues/594))
- Added the ability to set a plain text template for Commerce emails. ([#1106](https://github.com/craftcms/commerce/issues/1106))
- Added the `showCustomerInfoTab` config setting, which determines whether Edit User pages should show a “Customer Info” tab. ([#1037](https://github.com/craftcms/commerce/issues/1037))
- Added the ability to create a percentage-based discount on the order total. ([#438](https://github.com/craftcms/commerce/issues/438))
- Added the ability to sort by customer attributes on the Orders index page. ([#1089](https://github.com/craftcms/commerce/issues/1089))
- Added the ability to set the title label for products and variants per product type. ([#244](https://github.com/craftcms/commerce/issues/244))
- Added the ability to enable/disabled countries and states. ([#213](https://github.com/craftcms/commerce/issues/213))
- Added the ability to show customer info on the Orders index page.
- Added `craft\commerce\base\Stat`.
- Added `craft\commerce\base\StatInterface`.
- Added `craft\commerce\base\StatTrait`.
- Added `craft\commerce\controllers\CountriesController::actionUpdateStatus()`.
- Added `craft\commerce\controllers\DiscountsController::actionClearDiscountUses()`.
- Added `craft\commerce\controllers\DiscountsController::actionUpdateStatus()`.
- Added `craft\commerce\controllers\DiscountsController::DISCOUNT_COUNTER_TYPE_CUSTOMER`.
- Added `craft\commerce\controllers\DiscountsController::DISCOUNT_COUNTER_TYPE_EMAIL`.
- Added `craft\commerce\controllers\DiscountsController::DISCOUNT_COUNTER_TYPE_TOTAL`.
- Added `craft\commerce\controllers\LineItemStatuses`.
- Added `craft\commerce\controllers\OrdersController::_getTransactionsWIthLevelsTableArray()`.
- Added `craft\commerce\controllers\OrdersController::actionNewOrder()`.
- Added `craft\commerce\controllers\SalesController::actionUpdateStatus()`.
- Added `craft\commerce\controllers\StatesController::actionUpdateStatus()`.
- Added `craft\commerce\elements\Order::$origin`.
- Added `craft\commerce\elements\Order::$recalculationMode`.
- Added `craft\commerce\elements\Order::getAdjustmentsByType()`.
- Added `craft\commerce\elements\Order::getCustomerLinkHtml()`.
- Added `craft\commerce\elements\Order::hasLineItems()`.
- Added `craft\commerce\models\Country::$enabled`.
- Added `craft\commerce\models\Customer::getCpEditUrl()`.
- Added `craft\commerce\models\Discount::$totalDiscountUseLimit`.
- Added `craft\commerce\models\Discount::$totalDiscountUses`.
- Added `craft\commerce\models\LineItem::$lineItemStatusId`.
- Added `craft\commerce\models\LineItem::$privateNote`.
- Added `craft\commerce\models\ProductType::$titleLabel`.
- Added `craft\commerce\models\ProductType::$variantTitleLabel`.
- Added `craft\commerce\models\State::$enabled`.
- Added `craft\commerce\queue\ConsolidateGuestOrders`.
- Added `craft\commerce\records\Country::$enabled`.
- Added `craft\commerce\records\LineItemStatus`.
- Added `craft\commerce\records\Purchasable::$description`.
- Added `craft\commerce\records\State::$enabled`.
- Added `craft\commerce\services\Countries::getAllEnabledCountries`.
- Added `craft\commerce\services\Countries::getAllEnabledCountriesAsList`.
- Added `craft\commerce\services\Discounts::clearCustomerUsageHistoryById()`.
- Added `craft\commerce\services\Discounts::clearDiscountUsesById()`.
- Added `craft\commerce\services\Discounts::clearEmailUsageHistoryById()`.
- Added `craft\commerce\services\Discounts::getCustomerUsageStatsById()`.
- Added `craft\commerce\services\Discounts::getEmailUsageStatsById()`.
- Added `craft\commerce\services\Emails::getAllEnabledEmails()`.
- Added `craft\commerce\services\LineItemStatuses::EVENT_DEFAULT_LINE_ITEM_STATUS`.
- Added `craft\commerce\services\LineItemStatuses`.
- Added `craft\commerce\services\States::getAllEnabledStates`.
- Added `craft\commerce\services\States::getAllEnabledStatesAsList`.
- Added `craft\commerce\services\States::getAllEnabledStatesAsListGroupedByCountryId`.
- Added `craft\commerce\services\States::getAllStatesAsListGroupedByCountryId`.
- Added `craft\commerce\stats\AverageOrderTotal`.
- Added `craft\commerce\stats\NewCustomers`.
- Added `craft\commerce\stats\RepeatCustomers`.
- Added `craft\commerce\stats\TopCustomers`.
- Added `craft\commerce\stats\TopProducts`.
- Added `craft\commerce\stats\TopProductTypes`.
- Added `craft\commerce\stats\TopPurchasables`.
- Added `craft\commerce\stats\TotalOrders`.
- Added `craft\commerce\stats\TotalOrdersByCountry`.
- Added `craft\commerce\stats\TotalRevenue`.
- Added `craft\commerce\web\assets\chartjs\ChartJsAsset`.
- Added `craft\commerce\web\assets\deepmerge\DeepMerge`.
- Added `craft\commerce\web\assets\statwidgets\StatWidgets`.
- Added `craft\commerce\widgets\AverageOrderTotal`.
- Added `craft\commerce\widgets\NewCustomers`.
- Added `craft\commerce\widgets\RepeatCustomers`.
- Added `craft\commerce\widgets\TopCustomers`.
- Added `craft\commerce\widgets\TopProducts`.
- Added `craft\commerce\widgets\TopProductTypes`.
- Added `craft\commerce\widgets\TopPurchasables`.
- Added `craft\commerce\widgets\TotalOrders`.
- Added `craft\commerce\widgets\TotalOrdersByCountry`.
- Added `craft\commerce\widgets\TotalRevenue`.

## Changed
- When a customer logs in, and their current guest cart is empty, their most recent cart that had items in it will be restored as the new current cart.
- The date range picker on the Orders index page has been moved to the page toolbar, and now affects which orders are shown in the order listing and which orders are included in order exports, rather than just affecting the chart.
- The Edit Order page is now a Vue app.
- Order status change emails are triggered by a queue job for faster checkout.
- When adding a donation to the cart, supplying a `donationAmount` parameter is no longer required. (Donations will default to zero if omitted.)
- `commerce/cart/*` actions now call `craft\commerce\elements\Order::toArray()` when generating the cart array for JSON responses.
- `commerce/payments/pay` JSON responses now list payment form errors under `paymentFormErrors` rather than `paymentForm`.
- Customer records that are anonymous and orphaned are now deleted during garbage collection.
- Changed the default category relationship type on promotions from `sourceElement` to `element`. ([#984](https://github.com/craftcms/commerce/issues/984))
- The `purgeInactiveCartsDuration` and `activeCartDuration` config settings now support all value formats supported by `craft\cms\helpers\ConfigHelper::durationInSeconds()`. ([#1071](https://github.com/craftcms/commerce/issues/1071))
- The `commerce/customer-addresses/save` action no long forces primary shipping and billing addresses if they do not exist. ([#1069](https://github.com/craftcms/commerce/issues/1069))
- Moved `craft\commerce\services\States::getAllStatesAsList()` logic to `craft\commerce\services\States::getAllStatesAsListGroupedByCountryId()` to be consistent with other service methods.
- The `allowEmptyCartOnCheckout` config setting is now set to `false` by default.
- Discount usage conditions now apply to the discount as a whole, rather than just the coupon code.
- Discounts’ user and email usage counters can be cleared individually.
- Addresses no longer require a first and last name.
- Guest orders are now consolidated with other orders from the same customer immediately after an order is completed, rather than when a user logs in. ([#1062](https://github.com/craftcms/commerce/issues/1062))
- It is no longer possible to merge previous carts automatically using the `mergeCarts` param.
- Removed the `mergeCarts` parameter from `craft\commerce\services\Carts::getCart()`.

## Deprecated
- Deprecated `craft\commerce\elements\Order::getShouldRecalculateAdjustments()` and `setShouldRecalculateAdjustments()`. `craft\commerce\elements\Order::$recalculationMode` should be used instead.
- Deprecated `craft\commerce\serviced\Customers::consolidateOrdersToUser()`. `craft\commerce\queue\ConsolidateGuestOrders` jobs should be used instead.
- Deprecated `craft\commerce\services\Orders::cartArray()`. `craft\commerce\elements\Order::toArray()` should be used instead.

## Removed
- Removed the Customer Info field type. ([#1037](https://github.com/craftcms/commerce/issues/1037))
- Removed the `craft.commerce.availableShippingMethods` Twig property.
- Removed the `craft.commerce.cart` Twig property.
- Removed the `craft.commerce.countriesList` Twig property.
- Removed the `craft.commerce.customer` Twig property.
- Removed the `craft.commerce.discountByCode` Twig property.
- Removed the `craft.commerce.primaryPaymentCurrency` Twig property.
- Removed the `craft.commerce.statesArray` Twig property.
- Removed the `commerce/cart/remove-all-line-items` action.
- Removed the `commerce/cart/remove-line-item` action.
- Removed the `commerce/cart/update-line-item` action.
- Removed `craft\commerce\base\Purchasable::getPurchasableId()`.
- Removed `craft\commerce\controllers\ChartsController`.
- Removed `craft\commerce\controllers\DiscountsController::actionClearCouponUsageHistory()`.
- Removed `craft\commerce\controllers\DownloadController::actionExportOrder()`.
- Removed `craft\commerce\elements\db\OrderQuery::updatedAfter()`.
- Removed `craft\commerce\elements\db\OrderQuery::updatedBefore()`.
- Removed `craft\commerce\elements\db\SubscriptionQuery::subscribedAfter()`.
- Removed `craft\commerce\elements\db\SubscriptionQuery::subscribedBefore()`.
- Removed `craft\commerce\elements\Order::getOrderLocale()`.
- Removed `craft\commerce\elements\Order::updateOrderPaidTotal()`.
- Removed `craft\commerce\elements\Product::getSnapshot()`.
- Removed `craft\commerce\elements\Product::getUnlimitedStock()`.
- Removed `craft\commerce\elements\Variant::getSalesApplied()`.
- Removed `craft\commerce\helpers\Order::mergeOrders()`.
- Removed `craft\commerce\models\Address::getFullName()`.
- Removed `craft\commerce\models\Discount::$totalUses`.
- Removed `craft\commerce\models\Discount::$totalUseLimit`.
- Removed `craft\commerce\models\Discount::getFreeShipping()`.
- Removed `craft\commerce\models\Discount::setFreeShipping()`.
- Removed `craft\commerce\models\LineItem::fillFromPurchasable()`.
- Removed `craft\commerce\models\LineItem::getDescription()`. Use `craft\commerce\models\LineItem::$description` instead.
- Removed `craft\commerce\models\LineItem::getSku()`. Use `craft\commerce\models\LineItem::$sku` instead.
- Removed `craft\commerce\models\Order::getDiscount()`.
- Removed `craft\commerce\models\Order::getShippingCost()`.
- Removed `craft\commerce\models\Order::getTax()`.
- Removed `craft\commerce\models\Order::getTaxIncluded()`.
- Removed `craft\commerce\models\ShippingMethod::$amount`.
- Removed `craft\commerce\services\Countries::getAllCountriesListData()`.
- Removed `craft\commerce\services\Discounts::clearCouponUsageHistoryById()`.
- Removed `craft\commerce\services\Gateways::getAllFrontEndGateways()`.
- Removed `craft\commerce\services\ShippingMethods::getOrderedAvailableShippingMethods()`.
- Removed `craft\commerce\services\Reports::getOrdersExportFile()`.
- Removed `craft\commerce\models\Address::EVENT_REGISTER_ADDRESS_VALIDATION_RULES` event. Use `craft\base\Model::EVENT_DEFINE_RULES` instead.
- Removed `craft\commerce\services\Reports::EVENT_BEFORE_GENERATE_EXPORT` event. Use `craft\base\Element::EVENT_REGISTER_EXPORTERS` to create your own exports.
- Removed `craft\commerce\web\assets\RevenueWidgetAsset`.
- Removed `craft\commerce\widgets\Revenue`. Use `craft\commerce\widgets\TotalRevenue` instead.
- Removed the `phpoffice/phpspreadsheet` package dependency.

## 2.2.27 - 2021-03-17

### Fixed
- Fixed a bug where included taxes may not have shown up in order totals.

## 2.2.26 - 2021-03-03

### Fixed
- Fixed a bug where `craft\commerce\elements\Order::getTotalShippingCost()` wasn’t returning a value. ([#2027](https://github.com/craftcms/commerce/pull/2027))

## 2.2.25 - 2021-01-21

### Fixed
- Fixed a bug where comparing getTotalPaid and getTotal methods in `craft\commerce\elements\Order::getPaidStatus` returns invalid boolean value. ([#1836](https://github.com/craftcms/commerce/issues/1836))
- Fixed a bug that prevented a customer from unsubscribing from a subscription and deleting payment sources.

## 2.2.24 - 2020-11-16

### Fixed
- Fixed a bug when deleting an address as a customer throws an error when cart is not empty. ([#1874](https://github.com/craftcms/commerce/pull/1874))

## 2.2.23 - 2020-10-19

### Fixed
- Fixed a bug where addresses were incorrectly associated with a customer after logging in. ([#1227](https://github.com/craftcms/commerce/issues/1227))

## 2.2.22 - 2020-09-15

### Fixed
- Fixed a PHP error that could occur during line item validation on Yii 2.0.36. ([yiisoft/yii2#18175](https://github.com/yiisoft/yii2/issues/18175))
- Fixed a bug products were incorrectly showing as having sales when using the `hasSales` query parameter.
- Fixed a bug where it wasn’t possible to update the rate on a payment currency. ([#1547](https://github.com/craftcms/commerce/issues/1547))

## 2.2.21 - 2020-06-17

### Changed
- Improved handling of race conditions between processing a webhook and completing an order. ([#1510](https://github.com/craftcms/commerce/issues/1510))

### Fixed
- Fixed a bug where “Purchase Total” and “Purchase Quantity” discount conditions weren’t being applied correctly. ([#1389](https://github.com/craftcms/commerce/issues/1389))

## 2.2.20 - 2020-05-27

### Fixed
- Fixed a bug where free shipping discounts could be applied incorrectly. ([#1473](https://github.com/craftcms/commerce/issues/1473))

## 2.2.19 - 2020-04-15

### Fixed
- Fixed a bug where “Purchase Total” and “Purchase Quantity” discount conditions were not checked when removing shipping costs. ([#1321](https://github.com/craftcms/commerce/issues/1321))

## 2.2.18 - 2020-03-05

### Fixed
- Fixed an error that occurred when editing a product from a Products field. ([#1291](https://github.com/craftcms/commerce/pull/1291))
- Fixed an error that could occur when editing a variant’s stock value. ([#1306](https://github.com/craftcms/commerce/issues/1306))

## 2.2.17 - 2020-02-12

### Changed
- Improved the performance of the Orders index page.

## 2.2.16 - 2020-02-10

### Changed
- Improved the performance of the Orders index page.

### Fixed
- Fixed a bug where customers could get an “Address does not belong to customer” validation error incorrectly during checkout. ([#1227](https://github.com/craftcms/commerce/issues/1227))

## 2.2.15 - 2020-01-25

### Fixed
- Fixed a bug where sales were not being applied to the cart in some cases. ([#1206](https://github.com/craftcms/commerce/issues/1206))
- Fixed a validation error that occurred when saving an order status.
- All models now extend base model rules correctly.

## 2.2.14 - 2020-01-14

### Added
- Added `craft\commerce\services\Discounts::getAllActiveDiscounts()`.

### Fixed
- Fixed an error that occurred when calling `toArray()` on a payment currency model. ([#1200](https://github.com/craftcms/commerce/issues/1200))
- Fixed a bug where adding items to the cart was slow if there were several disabled or outdated discounts.

## 2.2.13 - 2019-12-19

### Fixed
- Fixed a bug where discounts were getting calculated incorrectly when using a “Per Email Limit” condition.

## 2.2.12 - 2019-12-19

### Fixed
- Fixed a PHP error that could occur when using coupon codes.
- Fixed a bug where taxes were getting calculated incorrectly when shipping costs were marked as having taxes included.

## 2.2.11 - 2019-12-16

### Fixed
- Fixed an infinite recursion bug that could occur when calculating discounts. ([#1182](https://github.com/craftcms/commerce/issues/1182))

## 2.2.10 - 2019-12-14

### Fixed
- Fixed an issue where discounts matching an order were referencing a missing method.

## 2.2.9 - 2019-12-13

### Added
- Order indexes can now have a “Coupon Code” column.
- Added the `resave/orders` and `resave/carts` commands.

### Deprecated
- Deprecated `craft\commerce\elements\Order::getTotalTaxablePrice()`.

### Fixed
- Fixed a bug where the wrong tax zone could be selected when editing a tax rate.
- Fixed a bug where some address data would be forgotten after completing an order.
- Fixed a typo in the `totalShipping` column heading on order exports. ([#1153](https://github.com/craftcms/commerce/issues/1153))
- Fixed a bug where discounts without a coupon code weren’t checking other discount conditions. ([#1144](https://github.com/craftcms/commerce/issues/1144))
- Fixed a SQL error that occurred when trying to save a long zip code condition formula. ([#1138](https://github.com/craftcms/commerce/issues/1138))
- Fixed an error that could occur on the Orders index page. ([#1160](https://github.com/craftcms/commerce/issues/1160))
- Fixed an error that could occur when executing a variant query with the `hasSales` param, if no one was logged in.
- Fixed an bug where it wasn’t possible to clear out the State field value on an address. ([#1162](https://github.com/craftcms/commerce/issues/1162))
- Fixed an error that occurred when marking an order as complete in the Control Panel. ([#1166](https://github.com/craftcms/commerce/issues/1166))
- Fixed an error that could occur when validating a product that had variants which didn’t have a SKU yet. ([#1165](https://github.com/craftcms/commerce/pull/1165))
- Fixed a bug where payments source active records could not retrieve their related gateway record. ([#1121](https://github.com/craftcms/commerce/pull/1121))
- Fixed a JavaScript error that occurred when editing shipping rules.

## 2.2.8 - 2019-11-21

### Added
- It’s now possible to sort products by Date Updated, Date Created and Promotable on the Products index page. ([#1101](https://github.com/craftcms/commerce/issues/1101))
- `totalTax`, `totalTaxIncluded`, `totalDiscount`, and `totalShipping` are now included on order exports. ([#719](https://github.com/craftcms/commerce/issues/719))
- Added the `COMMERCE_PAYMENT_CURRENCY` environment variable. ([#999](https://github.com/craftcms/commerce/pull/999))

### Fixed
- Fixed an error that could occur when deploying `project.yaml` changes to a new environment. ([#1085](https://github.com/craftcms/commerce/issues/1085))
- Fixed an issue where purchasables were added to the cart when the qty submitted was `0` (zero).
- Fixed a performance issue using the `craft\commerce\elements\db\VariantQuery::hasSales()` query param.
- Fixed an error that could occur with `dateCreated` when programmatically adding line items.

## 2.2.7 - 2019-10-30

### Changed
- `commerce/cart/*` requests now include estimated address data in their JSON responses. ([#1084](https://github.com/craftcms/commerce/issues/1084))

### Deprecated
- Deprecated `craft\commerce\models\Address::getFullName()`.

### Fixed
- Fixed an error that could occur when deploying `project.yaml` changes to a new environment. ([#1085](https://github.com/craftcms/commerce/issues/1085))
- Fixed a missing import. ([#1087](https://github.com/craftcms/commerce/issues/1087))
- Fixed a SQL error that occurred when eager-loading variants. ([#1093](https://github.com/craftcms/commerce/pull/1093))
- Fixed an error that occurred on the Orders index page if the “Shipping Business Name” column was shown.

## 2.2.6 - 2019-10-26

### Fixed
- Fixed a PHP error that occurred when rendering PDFs. ([#1072](https://github.com/craftcms/commerce/pull/1072))
- Fixed a PHP error that occurred when saving order statuses. ([#1082](https://github.com/craftcms/commerce/issues/1082))

## 2.2.5 - 2019-10-24

### Fixed
- Fixed formatting of customer info field.

## 2.2.4 - 2019-10-24

### Fixed
- Fixed a PHP error when loading the order in the CP. ([#1079](https://github.com/craftcms/commerce/issues/1079))
- Fixed a 404 error for missing JavaScript. ([#1078](https://github.com/craftcms/commerce/issues/1078))

## 2.2.3 - 2019-10-24

### Fixed
- Fixed a PHP error when calculating shipping or taxes in the cart. ([#1076](https://github.com/craftcms/commerce/issues/1076))
- Fixed a PHP error when saving a sale. ([#1075](https://github.com/craftcms/commerce/issues/1075))

## 2.2.2 - 2019-10-23

### Fixed
- Fixed a PHP error when calculating shipping or taxes in the cart.

## 2.2.1 - 2019-10-23

### Fixed
- Fixed a PostgreSQL migration issue.

## 2.2.0 - 2019-10-23

### Added
- Added the ability to produce estimated shipping and tax costs based on incomplete shipping and billing addresses. ([#514](https://github.com/craftcms/commerce/issues/514))
- Edit User pages now have a “Customer Info” tab.
- It’s now possible to view and create discounts directly from the Edit Product page.
- It’s now possible to delete customer addresses directly from the Edit User page. ([#171](https://github.com/craftcms/commerce/issues/171))
- Addresses can now have “Address 3”, “Full Name”, “Label”, “Notes”, and four custom fields.
- Email settings can now specify CC and Reply To email addresses.
- Discounts now have the option to ignore sales when applied (enabled by default for new discounts). ([#1008](https://github.com/craftcms/commerce/issues/1008))
- Shipping and tax zones can now have a dynamic zip code condition. ([#204](https://github.com/craftcms/commerce/issues/304))
- Tax rates can now have codes. ([#707](https://github.com/craftcms/commerce/issues/707))
- Countries can now be ordered manually. ([#224](https://github.com/craftcms/commerce/issues/224))
- Order statuses can now have descriptions. ([#1004](https://github.com/craftcms/commerce/issues/1004))
- Added support for using cards that require Strong Customer Authentication for subscriptions.
- Added the ability to resolve payment issues for subscriptions.
- Added the “Default View” setting, which determines which view should be shown by default when “Commerce” is selected in the global nav. ([#555](https://github.com/craftcms/commerce/issues/555))
- Added the `activeCartDuration` config setting. ([#959](https://github.com/craftcms/commerce/issues/959))
- Added the `allowEmptyCartOnCheckout` config setting, which determines whether a customer can check out with an empty cart. ([#620](https://github.com/craftcms/commerce/issues/620))
- Added the ability to pass additional variables to the PDF template. ([#599](https://github.com/craftcms/commerce/issues/599))
- Added the ability to override the “Cart updated” flash message by passing a `cartUpdatedNotice` parameter to the `commerce/cart/update-cart` action. ([#1038](https://github.com/craftcms/commerce/issues/1038))
- Added the `shortNumber` order query param.
- `commerce/cart/update-cart` requests can now specify `estimatedShippingAddress` and `estimatedBillingAddress` params.
- Added `craft\commerce\base\SubscriptionGatewayInterface::getBillingIssueDescription()`.
- Added `craft\commerce\base\SubscriptionGatewayInterface::getBillingIssueResolveFormHtml()`.
- Added `craft\commerce\base\SubscriptionGatewayInterface::getHasBillingIssues()`.
- Added `craft\commerce\controllers\BaseFrontEndController::EVENT_MODIFY_CART_INFO`. ([#1002](https://github.com/craftcms/commerce/issues/1002))
- Added `craft\commerce\elements\db\SubscriptionQuery::$dateSuspended`.
- Added `craft\commerce\elements\db\SubscriptionQuery::$hasStarted`.
- Added `craft\commerce\elements\db\SubscriptionQuery::$isSuspended`.
- Added `craft\commerce\elements\db\SubscriptionQuery::anyStatus()`.
- Added `craft\commerce\elements\db\SubscriptionQuery::dateSuspended()`.
- Added `craft\commerce\elements\db\SubscriptionQuery::hasStarted()`.
- Added `craft\commerce\elements\db\SubscriptionQuery::isSuspended()`.
- Added `craft\commerce\elements\Order::$estimatedBillingAddressId`.
- Added `craft\commerce\elements\Order::$estimatedBillingSameAsShipping`.
- Added `craft\commerce\elements\Order::$estimatedShippingAddressId`.
- Added `craft\commerce\elements\Order::getEstimatedBillingAddress()`.
- Added `craft\commerce\elements\Order::getEstimatedShippingAddress()`.
- Added `craft\commerce\elements\Order::setEstimatedBillingAddress()`.
- Added `craft\commerce\elements\Order::setEstimatedShippingAddress()`.
- Added `craft\commerce\elements\Subscription::$dateSuspended`.
- Added `craft\commerce\elements\Subscription::$hasStarted`.
- Added `craft\commerce\elements\Subscription::$isSuspended`.
- Added `craft\commerce\elements\Subscription::getBillingIssueDescription()`.
- Added `craft\commerce\elements\Subscription::getBillingIssueResolveFormHtml()`.
- Added `craft\commerce\elements\Subscription::getHasBillingIssues()`.
- Added `craft\commerce\models\Address::$isEstimated`.
- Added `craft\commerce\models\Customer::getActiveCarts()`.
- Added `craft\commerce\models\Customer::getInactiveCarts()`.
- Added `craft\commerce\models\OrderAdjustment::$isEstimated`.
- Added `craft\commerce\services\Sales::EVENT_AFTER_SAVE_SALE`. ([#622](https://github.com/craftcms/commerce/issues/622))
- Added `craft\commerce\services\Sales::EVENT_BEFORE_SAVE_SALE`. ([#622](https://github.com/craftcms/commerce/issues/622))
- Added `craft\commerce\test\fixtures\elements\ProductFixture`. ([#1009](https://github.com/craftcms/commerce/pull/1009))
- Added the `updateBillingDetailsUrl` config setting.
- Added the `suspended` status for Subscriptions.

### Changed
- Craft Commerce now required Craft CMS 3.3.0 or later.
- Edit Product pages no longer show SKU fields for new products or variants when the SKU will be automatically generated. ([#217](https://github.com/craftcms/commerce/issues/217))
- The View Order page now shows timestamps for “Order Completed”, “Paid”, and “Last Updated”. ([#1020](https://github.com/craftcms/commerce/issues/1020))
- The Orders index page now has unique URLs for each order status. ([#901](https://github.com/craftcms/commerce/issues/901))
- Orders now show whether they’ve been overpaid. ([#945](https://github.com/craftcms/commerce/issues/945))
- Carts now return their line items  `dateCreated DESC` in the cart by default. ([#1055](https://github.com/craftcms/commerce/pull/1055))
- Leading and trailing whitespace is now trimmed from all address fields.
- Coupon code usage is now tracked even for discounts with no limit set. ([#521](https://github.com/craftcms/commerce/issues/521))
- Variants now always include their product’s title in their search keywords. ([#934](https://github.com/craftcms/commerce/issues/934))
- The Subscriptions index page now includes “Failed to start” and “Payment method issue” sources.
- Subscriptions now get suspended if there are any payment issues.
- Expired orders are now purged during garbage collection rather than when viewing the Orders index page.
- Customer records that are not related to anything are now purged during garbage collection. ([#1045](https://github.com/craftcms/commerce/issues/1045))
- `commerce/cart/update-cart` requests now include line item adjustment data in their JSON response. ([#1014](https://github.com/craftcms/commerce/issues/1014))
- `craft\commerce\elements\Order::getTotalDiscount()` is no longer deprecated.
- `craft\commerce\elements\Order::getTotalShippingCost()` is no longer deprecated.
- `craft\commerce\elements\Order::getTotalTax()` is no longer deprecated.
- `craft\commerce\elements\Order::getTotalTaxIncluded()` is no longer deprecated.
- `craft\commerce\models\LineItem::getDiscount()` is no longer deprecated.
- `craft\commerce\models\LineItem::getShippingCost()` is no longer deprecated.
- `craft\commerce\models\LineItem::getTax()` is no longer deprecated.
- `craft\commerce\models\LineItem::getTaxIncluded()` is no longer deprecated.

### Deprecated
- Commerce Customer Info fields are now deprecated.
- Deprecated `craft\commerce\models\LineItem::getAdjustmentsTotalByType()`.
- Deprecated `craft\commerce\elements\Order::getAdjustmentsTotalByType()`.

### Fixed
- Fixed a PostgreSQL migration issue.
- Fixed a bug where the Orders index page was listing non-sortable fields as sort options. ([#933](https://github.com/craftcms/commerce/issues/993))
- Fixed a bug where timestamps on the View Order page weren’t respecting the user’s locale.
- Fixed a bug where product types’ site settings weren’t being added to the project config when a new site was created.
- Fixed a bug where order taxes weren’t accounting for discounted shipping costs. ([#1007](https://github.com/craftcms/commerce/issues/1007))
- Fixed a bug where orders’ `datePaid` attributes weren’t getting set to `null` after a refund. ([#1026](https://github.com/craftcms/commerce/pull/1026))
- Fixed a bug where order status handles could get a validation error if another order status with the same handle had been soft-deleted. ([#1027](https://github.com/craftcms/commerce/pull/1027))
- Fixed a bug where soft-deleted order statuses weren’t showing up in the History tab on View Order pages.
- Fixed a bug where breadcrumbs weren’t displaying correctly in the “Shipping” and “Tax” sections.
- Fixed an error that could occur when clicking “Refresh Payment History” on a canceled or expired subscription. ([#871](https://github.com/craftcms/commerce/issues/871))
- Fixed a bug where gateways that were disabled via `config/commerce-gateways.php` were still visible on the front-end. ([#1054](https://github.com/craftcms/commerce/issues/1054))
- Fixed a bug where it was possible to submit a zero-value donation. ([#820](https://github.com/craftcms/commerce/issues/820))
- Fixed a bug where line items’ `dateCreated` would get reset each time the cart was saved.
- Fixed a bug where all states were shown on the Store Location page regardless of which country was selected. ([#942](https://github.com/craftcms/commerce/issues/942))
- Fixed a bug where expired subscriptions were being identified as trials. ([#723](https://github.com/craftcms/commerce/issues/723))
- Fixed a bug where users’ addresses could be copied to impersonated users’ address books. ([#903](https://github.com/craftcms/commerce/issues/903))

## 2.1.13 - 2019-09-09

### Changed
- The “Status Email Address” and “From Name” settings now accept environment variables.

### Fixed
- Fixed a error when requesting a PDF URL in headless mode. ([#1011](https://github.com/craftcms/commerce/pull/1011))
- Fixed a bug where the “Download PDF” button wouldn’t show in the View Order page. ([#962](https://github.com/craftcms/commerce/issues/962))
- Fixed a bug where the <kbd>Command</kbd>/<kbd>Ctrl</kbd> + <kbd>S</kbd> shortcut didn’t work in General Settings.
- Fixed a bug where <kbd>Command</kbd>/<kbd>Ctrl</kbd> + <kbd>S</kbd> shortcut didn’t work in Store Location settings.
- Fixed a bug where users were forced to choose a tax category for order taxable subjects. ([#538](https://github.com/craftcms/commerce/issues/538))
- Fixed a bug where variants’ statuses were getting overridden by their product’s status. ([#926](https://github.com/craftcms/commerce/issues/926))
- Fixed a bug where Control Panel payments were incorrectly using the order’s previous payment source. ([#891](https://github.com/craftcms/commerce/issues/891))
- Fixed a bug where products’ shipping and tax categories weren’t getting updated if their selected shipping/tax category was no longer available. ([#688](https://github.com/craftcms/commerce/issues/688))
- Fixed a PHP error that occurred when entering an order description format on a product type that was longer than 255 characters. ([#989](https://github.com/craftcms/commerce/issues/989))
- Fixed a bug where emails were displaying the wrong timestamp for new orders. ([#882](https://github.com/craftcms/commerce/issues/882))
- Fixed a bug where the Products index page was not sorting correctly. ([#987](https://github.com/craftcms/commerce/issues/987))
- Fixed an error that could occur on payment when using a custom shipping method if the `requireShippingMethodSelectionAtCheckout` config setting was enabled.

## 2.1.12.1 - 2019-08-23

### Fixed
- Fixed a PHP error that could occur at checkout. ([#973](https://github.com/craftcms/commerce/pull/973))

## 2.1.12 - 2019-08-22

### Changed
- `craft\commerce\elements\Order::getPdfUrl()` no longer pre-renders the order PDF before returning the URL, improving performance. ([#962](https://github.com/craftcms/commerce/issues/962))

### Fixed
- Fixed a bug where order revenue charts weren’t showing the correct currency. ([#792](https://github.com/craftcms/commerce/issues/792))
- Fixed a bug where decimals were being stripped in locales that use commas as separators ([#592](https://github.com/craftcms/commerce/issues/592))
- Fixed a bug where sites with a large number of variants might not update properly when updating to Craft Commerce 2. ([#964](https://github.com/craftcms/commerce/issues/964))
- Fixed a bug where the “Purchase Total” discount condition would only save whole numbers. ([#966](https://github.com/craftcms/commerce/pull/966))
- Fixed a bug where products showed a blank validation error message when their variants had errors. ([#546](https://github.com/craftcms/commerce/issues/546))
- Fixed a bug where emails would ignore the “From Name” setting. ([#939](https://github.com/craftcms/commerce/issues/939))
- Fixed a bug where order adjustments were not being returned during PDF rendering. ([#960](https://github.com/craftcms/commerce/issues/960))
- Fixed a bug where the `commerce/payments/pay` action did not return order errors. ([#601](https://github.com/craftcms/commerce/issues/601))
- Fixed a SQL error that occurred when updating an order status with a very long message. ([#629](https://github.com/craftcms/commerce/issues/629))
- Fixed a JavaScript error that occurred when displaying product edit HUDs. ([#418](https://github.com/craftcms/commerce/issues/418))
- Fixed a PHP error that occurred when saving a product from an editor HUD. ([#958](https://github.com/craftcms/commerce/issues/958))
- Fixed an bug where the `requireShippingMethodSelectionAtCheckout` setting was being ignored.
- Fixed a bug that caused the order revenue chart to display incorrect data. ([#518](https://github.com/craftcms/commerce/issues/518))

## 2.1.11 - 2019-08-09

### Added
- Added the `cp.commerce.discount.edit` template hook. ([#936](https://github.com/craftcms/commerce/pull/936))
- Added `craft\commerce\services\Carts::getHasSessionCartNumber()`.
- Added `craft\commerce\services\Carts::getMergedCart()`.
- Added `craft\commerce\services\Discounts::EVENT_AFTER_DELETE_DISCOUNT`. ([#936](https://github.com/craftcms/commerce/pull/936))
- Added `craft\commerce\services\Discounts::EVENT_AFTER_SAVE_DISCOUNT`. ([#936](https://github.com/craftcms/commerce/pull/936))
- Added `craft\commerce\services\Discounts::EVENT_BEFORE_SAVE_DISCOUNT`. ([#936](https://github.com/craftcms/commerce/pull/936))
- Added `craft\commerce\services\Reports::EVENT_BEFORE_GENERATE_EXPORT`. ([#949](https://github.com/craftcms/commerce/pull/949))

### Changed
- Improved the performance of Craft Commerce 2 migrations.
- Users’ carts are no longer merged together automatically. Instead cart merging can be manually triggered by passing a `mergeCarts` param to the `commerce/cart/get-cart` and `commerce/cart/update-cart` actions. ([#947](https://github.com/craftcms/commerce/issues/947))
- After a logged-in user completes an order, their most recent incomplete cart is now loaded as the current cart in session.
- Order file exports are now cached in `storage/runtime/commerce-order-exports/` instead of `storage/runtime/temp/commerce-order-exports/`.
- The example templates now include client side polling to detect if the cart has changed in another tab or session.
- The example templates show more information about the cart to help with debugging.

### Removed
- Removed the `mergeLastCartOnLogin` setting.

### Fixed
- Fixed a bug where `craft/commerce/elements/Order::EVENT_BEFORE_ADD_LINE_ITEM` events had `$isNew` set incorrectly. ([#851](https://github.com/craftcms/commerce/pull/851))
- Fixed a bug where non-shippable purchasables were getting included in shipping price calculations.
- Fixed an error that occurred when clearing order caches.
- Fixed a bug where the `project-config/rebuild` command would remove the order field layout. ([#948](https://github.com/craftcms/commerce/issues/948))

### Security
- Fixed a data disclosure vulnerability.

## 2.1.10 - 2019-07-12

### Fixed
- Fixed a bug where all payments from the control panel were rejected. ([#928](https://github.com/craftcms/commerce/issues/928))

## 2.1.9 - 2019-07-10

### Security
- Fixed a data disclosure vulnerability.

## 2.1.8 - 2019-07-08

### Added
- Added the `resave/products` command (requires Craft 3.2).

### Changed
- Orders now include the full customer name as search keywords. ([#892](https://github.com/craftcms/commerce/issues/892))
- CSRF protection is now disabled for the `commerce/pay/complete-payment` controller action. ([#900](https://github.com/craftcms/commerce/issues/900))
- Leading and trailing whitespace is now trimmed from coupon codes. ([#894](https://github.com/craftcms/commerce/issues/894))

### Fixed
- Fixed a bug where the `lineItems` array wasn’t getting indexed correctly when calling `toArray()` on an order.
- Fixed a PHP error that occurred when `commerce/subscriptions/*` actions had validation errors. ([#918](https://github.com/craftcms/commerce/issues/918))
- Fixed a PHP error that occurred when retrieving line items with no option data. ([#897](https://github.com/craftcms/commerce/issues/897))
- Fixed a bug where shipping and billing addresses weren’t being set correctly when saving an order. ([#922](https://github.com/craftcms/commerce/issues/922))
- Fixed a bug where it was possible to pay with a disabled gateway. ([#912](https://github.com/craftcms/commerce/issues/912))
- Fixed a bug where Edit Subscription pages weren’t showing custom tabs. ([#884](https://github.com/craftcms/commerce/issues/884))
- Fixed a bug where an empty cart was created unnecessarily when a user logged in. ([#906](https://github.com/craftcms/commerce/issues/906))
- Fixed a bug where `craft\commerce\services\Plans::getAllEnabledPlans()` was returning archived subscription plans. ([#916](https://github.com/craftcms/commerce/issues/916))

## 2.1.7 - 2019-06-11

### Fixed
- Fixed a SQL error that would occur when upgrading Craft Commerce. ([#829](https://github.com/craftcms/commerce/issues/829))
- Fixed an bug that could stop more that one sale being applied to a purchasable. ([#839](https://github.com/craftcms/commerce/issues/839))
- Fixed a SQL error that could occur when saving a line item with an emoji in it.([#886](https://github.com/craftcms/commerce/issues/886))
- Fixed an error that could occur on the order index page when viewing carts with certain columns enabled. ([#876](https://github.com/craftcms/commerce/issues/876))
- Fixed a bug on the order index page where carts without transactions would show up under the “Attempted Payments” source. ([#880](https://github.com/craftcms/commerce/issues/880))

## 2.1.6.1 - 2019-05-14

### Added
- Added the `mergeLastCartOnLogin` config setting.

## 2.1.6 - 2019-05-14

### Added
- Added `craft\commerce\elements\db\VariantQuery::minQty()` and `maxQty()`. ([#827](https://github.com/craftcms/commerce/pull/827))

### Changed
- Line item options are no longer forced to be sorted alphabetically by key.

### Fixed
- Fixed a bug where product and variant snapshots were missing data. ([#846](https://github.com/craftcms/commerce/issues/846))
- Fixed an SQL error that occurred when saving a SKU that was too long. ([#853](https://github.com/craftcms/commerce/issues/853))
- Fixed an SQL error that could occur when attempting to update a soft-deleted cart. ([#854](https://github.com/craftcms/commerce/issues/854))
- Fixed an SQL error that could occur when attempting to add a line item to a completed order. ([#860](https://github.com/craftcms/commerce/issues/860))
- Fixed a bug where line item quantity validators weren’t checking for updated quantities. ([#855](https://github.com/craftcms/commerce/pull/855))
- Fixed a bug where it wasn’t possible to query for unpaid orders. ([#858](https://github.com/craftcms/commerce/pull/858))
- Fixed a JavaScript error that could occur on the Order index page. ([#862](https://github.com/craftcms/commerce/pull/862))
- Fixed a bug where the “Create discount…” product action wasn’t pre-populating discounts’ variant conditions.
- Fixed a bug that could prevent a purchasable from being added to the cart when using multi-add.

## 2.1.5.2 - 2019-05-08

## Fixed
- Fixed a missing import. ([#845](https://github.com/craftcms/commerce/issues/845))
- Fixed an error that could occur when a customer logged in.
- Fixed an error that occurred when saving a sale. ([#837](https://github.com/craftcms/commerce/issues/837))

## 2.1.5.1 - 2019-05-07

### Fixed
- Fixed a missing import. ([#843](https://github.com/craftcms/commerce/issues/843))

## 2.1.5 - 2019-05-07

### Added
- Added `craft\commerce\helpers\Order::mergeDuplicateLineItems()`.
- Added `craft\commerce\helpers\Order::mergeOrders()`.

### Changed
- Customers’ previous cart items are now merged into the active cart on login.

### Fixed
- Fixed a bug where Craft Commerce would create a subscription even if the card was declined.
- Fixed an error that could occur when creating a subscription using the Dummy gateway.

## 2.1.4 - 2019-04-29

### Added
- Added `craft\commerce\base\SubscriptionResponseInterface::isInactive()`.

### Changed
- Improved performance of the Orders index page. ([#828](https://github.com/craftcms/commerce/issues/828))
- `commerce/cart/*` action JSON responses now list cart errors under an `errors` key.
- Craft Commerce now correctly typecasts all boolean and integer values saved to the project config.

### Fixed
- Fixed a SQL error that occurred when duplicate line items were added the cart. ([#506](https://github.com/craftcms/commerce/issues/506))
- Fixed a PHP error on the View Order page when viewing inactive carts. ([#826](https://github.com/craftcms/commerce/issues/826))
- Fixed a deprecation warning. ([#825](https://github.com/craftcms/commerce/issues/825))
- Fixed a bug where the wrong variant could be set as the default when saving a product. ([#830](https://github.com/craftcms/commerce/issues/830))
- Fixed a bug that prevented plugins and modules from adding custom index table attributes. ([#832](https://github.com/craftcms/commerce/pull/832))

## 2.1.3.1 - 2019-04-10

### Fixed
- Fixed a bug where `project.yaml` changes weren’t always getting picked up.

## 2.1.3 - 2019-04-03

### Added
- Added support for user registration on checkout. ([#472](https://github.com/craftcms/commerce/issues/472))
- Added “Capture Payment” and “Refund Payment” user permissions. ([#788](https://github.com/craftcms/commerce/pull/788))
- Added support for the `project-config/rebuild` command.
- Added the `validateBusinessTaxIdAsVatId` setting, which can be set to `true` from `config/commerce.php`.
- Added `craft\commerce\services\Addresses::EVENT_AFTER_DELETE_ADDRESS`. ([#810](https://github.com/craftcms/commerce/pull/810))

### Changed
- Craft Commerce now requires Craft CMS 3.1.20 or later.
- An `order` variable is now available to payment forms when a payment is made from the Control Panel.
- Ajax requests to `commerce/cart/get-cart` now include the price of available shipping methods in the response.

### Fixed
- Fixed a bug where an order could be listed multiple times under “Attempted Payments” on order pages. ([#602](https://github.com/craftcms/commerce/issues/602))
- Fixed a bug where product sources did not fully support using UIDs. ([#781](https://github.com/craftcms/commerce/issues/781))
- Fixed a bug where non-admin users could get a 403 error when attempting to edit subscriptions. ([#722](https://github.com/craftcms/commerce/issues/722))
- Fixed a bug where products’ `defaultVariantId` was not getting set on the first save. ([#796](https://github.com/craftcms/commerce/issues/796))
- Fixed a PHP error when querying for products with the `hasSales` param.
- Fixed a bug where product metadata wasn’t available to templates on Live Preview requests.
- Fixed a bug where the wrong Craft Commerce subnav item could appear selected in the Control Panel.
- Fixed a bug where taxes could be incorrectly calculated if included taxes had been removed from the price.
- Fixed a bug where additional discounts could be incorrectly applied to an order if multiple products had been added to the cart at the same time. ([#797](https://github.com/craftcms/commerce/issues/797))
- Fixed a bug where products’ Post Dates could be incorrect on first save. ([#774](https://github.com/craftcms/commerce/issues/774))
- Fixed a bug where emails weren’t getting sent when the “Status Email Address” setting was set. ([#806](https://github.com/craftcms/commerce/issues/806))
- Fixed a bug where order status email changes in `project.yaml` could be ignored. ([#802](https://github.com/craftcms/commerce/pull/802))
- Fixed a PHP error that occurred when submitting a `paymentCurrency` parameter on a `commerce/payments/pay` request. ([#809](https://github.com/craftcms/commerce/pull/809))

## 2.1.2 - 2019-03-12

### Added
- Added a “Minimum Total Price Strategy” setting that allows the minimum order price be negative (default), at least zero, or at least the shipping cost. ([#651](https://github.com/craftcms/commerce/issues/651))
- Added `craft\commerce\elements\Order::getTotal()` to get the price of the order before any pricing strategies.
- Added `craft\commerce\base\SubscriptionGatewayInterface::refreshPaymentHistory()` method that should be used to refresh all payments on a subscription.
- Added `craft\commerce\base\SubscriptionGateway::refreshPaymentHistory()` method to fulfill the interface requirements.

### Changed
- The `commerce-manageSubscriptions` permission is now required (instead of admin permissions) to manage another user’s subscriptions. ([#722](https://github.com/craftcms/commerce/issues/722))

## 2.1.1.1 - 2019-03-01

### Fixed
- Fixed a PHP error raised when a discount adjustment was applied to the cart.

## 2.1.1 - 2019-03-11

### Changed
- Improved performance when listing products with sales that have many category conditions. ([#758](https://github.com/craftcms/commerce/issues/758))
- Purchasable types are now responsible to ensure SKU uniqueness when they are restored from being soft-deleted.

### Fixed
- Fixed a bug where orders could receive free shipping on some line items when an expired coupon code had been entered. ([#777](https://github.com/craftcms/commerce/issues/777))
- Fixed a bug where variants weren’t enforcing required field validation. ([#761](https://github.com/craftcms/commerce/issues/761))
- Fixed a bug where the sort order wasn’t getting saved correctly for new order statuses.
- Fixed the breadcrumb navigation on Store Settings pages. ([#769](https://github.com/craftcms/commerce/issues/769))
- Fixed an error that occurred when viewing an order for a soft-deleted user. ([#771](https://github.com/craftcms/commerce/issues/771))
- Fixed an error that could occur when saving a new gateway.
- Fixed a SQL error that occurred when saving a purchasable with the same SKU as a soft-deleted purchasable. ([#718](https://github.com/craftcms/commerce/issues/718))

## 2.1.0.2 - 2019-02-25

### Fixed
- Fixed more template loading errors on Craft Commerce settings pages. ([#751](https://github.com/craftcms/commerce/issues/751))

## 2.1.0.1 - 2019-02-25

### Fixed
- Fixed some template loading errors on Craft Commerce settings pages. ([#751](https://github.com/craftcms/commerce/issues/751))

## 2.1.0 - 2019-02-25

### Added
- Added a new Donation built-in purchasable type. ([#201](https://github.com/craftcms/commerce/issues/201))
- Added a new “Manage store settings” user permission, which determines whether the current user is allowed to manage store settings.
- Added `craft\commerce\elements\Order::EVENT_BEFORE_ADD_LINE_ITEM`.
- Added `craft\commerce\base\PurchasableInterface::getIsTaxable()`.
- Added `craft\commerce\base\PurchasableInterface::getIsShippable()`.
- Added `craft\commerce\models\Discount::getHasFreeShippingForMatchingItems()`.

### Changed
- Discounts can now apply free shipping on the whole order. ([#745](https://github.com/craftcms/commerce/issues/745))
- The “Settings” section has been split into “System Settings”, “Store Settings”, “Shipping”, and “Tax” sections.
- The Orders index page now shows total order counts.
- The `commerce/payments/pay` action JSON response now include the order data. ([#715](https://github.com/craftcms/commerce/issues/715))
- The `craft\commerce\elements\Order::EVENT_AFTER_ORDER_PAID` event is now fired after the `craft\commerce\elements\Order::EVENT_AFTER_COMPLETE_ORDER` event. ([#670](https://github.com/craftcms/commerce/issues/670))

### Deprecated
- `craft\commerce\models\Discount::$freeShipping` is deprecated. `getHasFreeShippingForMatchingItems()` should be used instead.

### Fixed
- Fixed an bug where multiple shipping discounts could result in a negative shipping cost.
- Fixed a validation error that occurred when attempting to apply a coupon with a per-email limit, if the cart didn’t have a customer email assigned to it yet.
- `commerce/cart/*` actions’ JSON responses now encode all boolean attributes correctly.
- `commerce/customer-addresses/*` actions’ JSON responses now include an `errors` array if there were any issues with the request.
- Fixed a bug where the order field layout could be lost when upgrading from Craft Commerce 1 to 2. ([#668](https://github.com/craftcms/commerce/issues/668))
- Fixed a bug where line item update requests could result in line items being removed if the `qty` parameter was missing.
- Fixed a bug where coupon codes weren’t being removed from carts when no longer valid. ([#711](https://github.com/craftcms/commerce/issues/711))
- Fixed a bug that could prevent a payment gateway from being modified. ([#656](https://github.com/craftcms/commerce/issues/656))
- Fixed a bug that prevented shipping and tax settings from being modified when the `allowAdminChanges` config setting was set to `false`.
- Fixed a PHP error that occurred when saving a product that was marked as disabled. ([#683](https://github.com/craftcms/commerce/pull/683))
- Fixed a PHP error that occurred when trying to access a soft-deleted cart from the front-end. ([#700](https://github.com/craftcms/commerce/issues/700))

## 2.0.4 - 2019-02-04

### Fixed
- Fixed a PHP error when recalculating tax.

### Added
- Added additional useful information when logging email rendering errors. ([#669](https://github.com/craftcms/commerce/pull/669))

## 2.0.3 - 2019-02-02

### Added
- Added the “Tax is included in price” tax setting for Craft Commerce Lite. ([#654](https://github.com/craftcms/commerce/issues/654))

### Changed
- Soft-deleted products are now restorable.
- Craft Commerce project config settings are now removed when Craft Commerce is uninstalled.

### Fixed
- Fixed an error that occurred when upgrading to Craft Commerce 2 with a database that had missing constraints on the `commerce_orderhistories` table.
- Fixed a bug where sale conditions could be lost when upgrading to Craft Commerce 2. ([#626](https://github.com/craftcms/commerce/issues/626))
- Fixed a PHP error that occurred when saving a product type. ([#645](https://github.com/craftcms/commerce/issues/645))
- Fixed a bug that prevented products from being deleted. ([#650](https://github.com/craftcms/commerce/issues/650))
- Fixed a PHP error that occurred when deleting the cart’s line item on Craft Commerce Lite. ([#639](https://github.com/craftcms/commerce/pull/639))
- Fixed a bug where Craft Commerce’s general settings weren’t saving. ([#655](https://github.com/craftcms/commerce/issues/655))
- Fixed a missing import. ([#643](https://github.com/craftcms/commerce/issues/643))
- Fixed a bug that caused an incorrect tax rate calculation when included taxes had been removed from the price.
- Fixed a SQL error that occurred when saving a tax rate without a tax zone selected. ([#667](https://github.com/craftcms/commerce/issues/667))
- Fixed an error that occurred when refunding a transaction with a localized currency format. ([#659](https://github.com/craftcms/commerce/issues/659))
- Fixed a SQL error that could occur when saving an invalid discount. ([#673](https://github.com/craftcms/commerce/issues/673))
- Fixed a bug where it wans’t possible to add non-numeric characters to expiry input in the default credit card form. ([#636](https://github.com/craftcms/commerce/issues/636))

## 2.0.2 - 2019-01-23

### Added
- Added the new Craft Commerce Lite example templates folder `templates/buy`, this is in addition to the existing Craft Commerce Pro example templates folder `templates/shop`.

### Fixed
- Fixed a PHP error raised when extending the `craft\commerce\base\ShippingMethod` class. ([#634](https://github.com/craftcms/commerce/issues/634))
- Fixed a PHP error that occurred when viewing an order that used a since-deleted shipping method.

## 2.0.1 - 2019-01-17

### Changed
- Renamed the shipping rule condition from “Mimimum order price” to “Minimum order value” which clarifies the condition is based on item value before discounts and tax.
- Renamed the shipping rule condition from “Maximum order price” to “Maximum order value” which clarifies the condition is based on item value before discounts and tax.

### Fixed
- Fixed an issue where the “Total Paid”, “Total Price”, and “Total Shipping Cost” Order index page columns were showing incorrect values. ([#632](https://github.com/craftcms/commerce/issues/632))
- Fixed an issue where custom field validation errors did not show up on the View Order page. ([#580](https://github.com/craftcms/commerce/issues/580))

## 2.0.0 - 2019-01-15

### Added
- Craft Craft Commerce has been completely rewritten for Craft CMS 3.
- Emails, gateways, order fields, order statuses, product types, and subscription fields are now stored in the project config.
- Added support for Craft 3.1 project config support.
- Gateways can now provide recurring subscription payments. ([#257](https://github.com/craftcms/commerce/issues/257))
- Added the Store Location setting.
- Customers can now save their credit cards or payment sources stored as tokens in Craft Commerce so customers don’t need to enter their card number on subsequent checkouts. ([#21](https://github.com/craftcms/commerce/issues/21))
- Any custom purchasable can now have sales and discounts applied to them.
- Sales and discounts can now be set on categories of products or purchasables.
- Customers can now set their primary default shipping and billing addresses in their address book.
- It’s now possible to export orders as CSV, ODS, XSL, and XLSX, from the Orders index page. ([#222](https://github.com/craftcms/commerce/issues/222))
- Orders can now have custom-formatted, sequential reference numbers. ([#184](https://github.com/craftcms/commerce/issues/184))
- The Orders index page now has an “Attempted Payments” source that shows incomplete carts that had a payment processing issue.
- Variant indexes can now have a “Product” column.
- Order indexes can now have “Total Tax” and “Total Included Tax” columns.
- The cart now defaults to the first cheapest available shipping method if no shipping method is set, or the previously-selected method is not available.
- Products now have an “Available for purchase” checkbox, making it possible to have a live product that isn’t available for purchase yet. ([#345](https://github.com/craftcms/commerce/issues/345))
- Added the ability to place a note on a refund transaction.
- Added a “Copy reference tag” Product element action.
- Added additional ways for sales promotions to affect the price of matching products.
- All credit card gateways are now provided as separate plugins.
- A custom PDF can now be attached to any order status email.
- Multiple purchasables can now be added to the cart in the same request. ([#238](https://github.com/craftcms/commerce/issues/238))
- Multiple line items can now be updated in the same request. ([#357](https://github.com/craftcms/commerce/issues/357))
- The `commerce/cart/update-cart` action will now remove items from the cart if a quantity of zero is submitted.
- `commerce/cart/*` actions’ JSON responses now include any address errors.
- The cart can now be retrieved as JSON with the `commerce/cart/get-cart` action.
- Added the `craft.variants()` Twig function, which returns a new variant query.
- Added the `craft.subscriptions()` Twig function, which returns a new subscription query.
- Product queries now have an `availableForPurchase` param.
- Variant queries now have a `price` param.
- Variant queries now have a `hasSales` param.
- Order queries now have a `hasTransactions` param.
- Added `cract\commerce\services\ProductTypes::getProductTypesByShippingCategoryId()`.
- Added `cract\commerce\services\ProductTypes::getProductTypesByTaxCategoryId()`.
- Added `craft\commerce\adjustments\Discount::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED`.
- Added `craft\commerce\base\ShippingMethod`.
- Added `craft\commerce\elements\Order::$paidStatus`.
- Added `craft\commerce\elements\Order::EVENT_AFTER_ADD_LINE_ITEM`.
- Added `craft\commerce\elements\Order::EVENT_AFTER_COMPLETE_ORDER`.
- Added `craft\commerce\elements\Order::EVENT_AFTER_ORDER_PAID`.
- Added `craft\commerce\elements\Order::EVENT_BEFORE_COMPLETE_ORDER`.
- Added `craft\commerce\elements\Order::getAdjustmentsTotalByType()`.
- Added `craft\commerce\elements\Variant::EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT`.
- Added `craft\commerce\elements\Variant::EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT`.
- Added `craft\commerce\elements\Variant::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT`.
- Added `craft\commerce\elements\Variant::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT`.
- Added `craft\commerce\models\Customer::getPrimaryBillingAddress()`.
- Added `craft\commerce\models\Customer::getPrimaryShippingAddress()`.
- Added `craft\commerce\models\LineItem::getAdjustmentsTotalByType()`.
- Added `craft\commerce\services\Addresses::EVENT_AFTER_SAVE_ADDREESS`.
- Added `craft\commerce\services\Addresses::EVENT_BEFORE_SAVE_ADDREESS`.
- Added `craft\commerce\services\Discounts::EVENT_BEFORE_MATCH_LINE_ITEM`.
- Added `craft\commerce\services\Emails::EVENT_AFTER_SAVE_EMAIL`.
- Added `craft\commerce\services\Emails::EVENT_AFTER_SAVE_EMAIL`.
- Added `craft\commerce\services\Emails::EVENT_AFTER_SEND_EMAIL`.
- Added `craft\commerce\services\Emails::EVENT_BEFORE_DELETE_EMAIL`.
- Added `craft\commerce\services\Emails::EVENT_BEFORE_SAVE_EMAIL`.
- Added `craft\commerce\services\Emails::EVENT_BEFORE_SEND_EMAIL`.
- Added `craft\commerce\services\Gateways::EVENT_REGISTER_GATEWAY_TYPES`.
- Added `craft\commerce\services\LineItems::EVENT_AFTER_SAVE_LINE_ITEM`.
- Added `craft\commerce\services\LineItems::EVENT_BEFORE_POPULATE_LINE_ITEM`.
- Added `craft\commerce\services\LineItems::EVENT_BEFORE_SAVE_LINE_ITEM`.
- Added `craft\commerce\services\LineItems::EVENT_CREATE_LINE_ITEM`.
- Added `craft\commerce\services\OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS`.
- Added `craft\commerce\services\OrderHistories::EVENT_ORDER_STATUS_CHANGE`.
- Added `craft\commerce\services\OrderStatuses::archiveOrderStatusById()`.
- Added `craft\commerce\services\Payments::EVENT_AFTER_CAPTURE_TRANSACTION`.
- Added `craft\commerce\services\Payments::EVENT_AFTER_CAPTURE_TRANSACTION`.
- Added `craft\commerce\services\Payments::EVENT_AFTER_PROCESS_PAYMENT`.
- Added `craft\commerce\services\Payments::EVENT_BEFORE_CAPTURE_TRANSACTION`.
- Added `craft\commerce\services\Payments::EVENT_BEFORE_PROCESS_PAYMENT`.
- Added `craft\commerce\services\Payments::EVENT_BEFORE_REFUND_TRANSACTION`.
- Added `craft\commerce\services\PaymentSources::EVENT_AFTER_SAVE_PAYMENT_SOURCE`.
- Added `craft\commerce\services\PaymentSources::EVENT_BEFORE_SAVE_PAYMENT_SOURCE`.
- Added `craft\commerce\services\PaymentSources::EVENT_DELETE_PAYMENT_SOURCE`.
- Added `craft\commerce\services\PaymentSources`.
- Added `craft\commerce\services\Plans::EVENT_AFTER_SAVE_PLAN`.
- Added `craft\commerce\services\Plans::EVENT_ARCHIVE_PLAN`.
- Added `craft\commerce\services\Plans::EVENT_BEFORE_SAVE_PLAN`.
- Added `craft\commerce\services\Plans`.
- Added `craft\commerce\services\Purchasables::EVENT_REGISTER_PURCHASABLE_ELEMENT_TYPES`.
- Added `craft\commerce\services\Sales::EVENT_BEFORE_MATCH_PURCHASABLE_SALE`.
- Added `craft\commerce\services\ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS`.
- Added `craft\commerce\services\Subscriptions::EVENT_AFTER_CANCEL_SUBSCRIPTION`.
- Added `craft\commerce\services\Subscriptions::EVENT_AFTER_CREATE_SUBSCRIPTION`.
- Added `craft\commerce\services\Subscriptions::EVENT_AFTER_REACTIVATE_SUBSCRIPTION`.
- Added `craft\commerce\services\Subscriptions::EVENT_AFTER_SWITCH_SUBSCRIPTION`.
- Added `craft\commerce\services\Subscriptions::EVENT_BEFORE_CANCEL_SUBSCRIPTION`.
- Added `craft\commerce\services\Subscriptions::EVENT_BEFORE_CREATE_SUBSCRIPTION`.
- Added `craft\commerce\services\Subscriptions::EVENT_BEFORE_REACTIVATE_SUBSCRIPTION`.
- Added `craft\commerce\services\Subscriptions::EVENT_BEFORE_SWITCH_SUBSCRIPTION`.
- Added `craft\commerce\services\Subscriptions::EVENT_BEFORE_UPDATE_SUBSCRIPTION`.
- Added `craft\commerce\services\Subscriptions::EVENT_EXPIRE_SUBSCRIPTION`.
- Added `craft\commerce\services\Subscriptions::EVENT_RECEIVE_SUBSCRIPTION_PAYMENT`.
- Added `craft\commerce\services\Subscriptions`.
- Added `craft\commerce\services\TaxCategories::getAllTaxCategoriesAsList()`.
- Added `craft\commerce\services\Transactions::EVENT_AFTER_SAVE_TRANSACTION`.

### Changed
- Payment Methods are now called “Gateways”.
- Order statuses are now archived instead of deleted.
- Product types can no longer select applicable shipping categories. Instead, shipping categories select applicable product types.
- Product types can no longer select applicable tax categories. Instead, tax categories select applicable product types.
- Order status messages can now be longer than 255 characters. ([#465](https://github.com/craftcms/commerce/issues/465)
- Product and variant custom field data is no longer included in the line item snapshot by default for performance reasons. Use the new snapshot events to manually snapshot custom field data.
- Variant titles are now prefixed by their products’ titles.
- Last addresses used by customers are no longer stored. Instead, customers have primary shipping and billing addresses.
- The `paymentMethodSettings` config setting was renamed to `gatewaySettings`, and it now uses handles to reference gateways instead of IDs.
- The `sendCartInfoToGateways` was renamed to `sendCartInfo,` and is a per-gateway setting.
- The payment method overrides in `config/commerce.php` have been moved to `config/commerce-gateway.php`.
- The `craft.commerce.availableShippingMethods` Twig variable has been replaced with `craft.commerce.carts.cart.availableShippingMethods`.
- The `craft.commerce.cart` Twig variable has been replaced with `craft.commerce.carts.cart`.
- The `craft.commerce.countries` Twig variable has been replaced with `craft.commerce.countries.allCountries`.
- The `craft.commerce.countriesList` Twig variable has been replaced with `craft.commerce.countries.allCountriesAsList`.
- The `craft.commerce.currencies` Twig variable has been replaced with `craft.commerce.currencies.allCurrencies`.
- The `craft.commerce.customer` Twig variable has been replaced with `craft.commerce.customers.customer`.
- The `craft.commerce.discountByCode` Twig variable has been replaced with `craft.commerce.discounts.discountByCode`.
- The `craft.commerce.discounts` Twig variable has been replaced with `craft.commerce.discounts.allDiscounts`.
- The `craft.commerce.orders` Twig variable has been replaced with `craft.orders()`.
- The `craft.commerce.orderStatuses` Twig variable has been replaced with `craft.commerce.orderStatuses.allOrderStatuses`.
- The `craft.commerce.paymentCurrencies` Twig variable has been replaced with `craft.commerce.paymentCurrencies.allPaymentCurrencies`.
- The `craft.commerce.paymentMethods` Twig variable has been replaced with `craft.commerce.gateways.allCustomerEnabledGateways`.
- The `craft.commerce.primaryPaymentCurrency` Twig variable has been replaced with `craft.commerce.paymentCurrencies.primaryPaymentCurrency`.
- The `craft.commerce.products` Twig variable has been replaced with `craft.products()`.
- The `craft.commerce.productTypes` Twig variable has been replaced with `craft.commerce.productTypes.allProductTypes`.
- The `craft.commerce.sales` Twig variable has been replaced with `craft.commerce.sales.allSales`.
- The `craft.commerce.shippingCategories` Twig variable has been replaced with `craft.commerce.shippingCategories.allShippingCategories`.
- The `craft.commerce.shippingMethods` Twig variable has been replaced with `craft.commerce.shippingMethods.allShippingMethods`.
- The `craft.commerce.shippingZones` Twig variable has been replaced with `craft.commerce.shippingZones.allShippingZones`.
- The `craft.commerce.states` Twig variable has been replaced with `craft.commerce.states.allStates`.
- The `craft.commerce.statesArray` Twig variable has been replaced with `craft.commerce.states.allStatesAsList`.
- The `craft.commerce.taxCategories` Twig variable has been replaced with `craft.commerce.taxCategories.allTaxCategories`.
- The `craft.commerce.taxRates` Twig variable has been replaced with `craft.commerce.taxRates.allTaxRates`.
- The `craft.commerce.taxZones` Twig variable has been replaced with `craft.commerce.taxZones.allTaxZones`.
- The `craft.commerce.variants` Twig variable has been replaced with `craft.variants()`.
- `Customer::$lastUsedBillingAddress` has been replaced with `$primaryBillingAddress`.
- `Customer::$lastUsedShippingAddress` has been replaced with `$primaryShippingAddres`.
- `OrderAdjustment::$optionsJson` was renamed to `$sourceSnapshot`.
- `Variant::getSalesApplied()` was renamed to `getSales()`.
- `Variant::setSalesApplied()` was renamed to `setSales()`.
- The Shipping Rule interface now expects a shipping category ID passed to each rate method.
- Any custom shipping method classes should now extend `craft\commerce\base\ShippingMethod`.
- All hooks have been replaced by events.
- Replaced `customer.lastUsedShippingAddress` and `customer.lastUsedBillingAddress` with `customer.primaryBillingAddress` and `customer.primaryShippingAddress`.
- Vat ID validation is now powered by the “vat.php” library.

### Removed
- Removed the `cartCookieDuration` config setting. All carts are now related to craft php session and not their own cookie.
- Removed the `requireEmailForAnonymousPayments` config setting, as completed order now always require the correct email address to make anonymous payments on orders.
- Removed `baseShipping`, `baseDiscount`, `baseTax`, `baseTaxIncluded` attributes from the order model. Orders now have order-level adjustments.
- Removed `shipping`, `discount`, `tax`, `taxIncluded` attributes from the line item model. Line items now have line item level adjustments.
- Removed `PurchasableInterface::validateLineItem()`. `getLineItemRules()` should be used instead.
- Removed the `deleteOrderStatusById()` method on the `OrderStatuses` service.
- Removed the `OrderSettings` model, record, and service.
- Removed the `getCountryByAttributes()` method from the `Countries` service.
- Removed the `getStatesByAttributes()` method from the `States` service.
- Removed the `getLastUsedBillingAddress()` and `getLatUsedShippingAddress()` methods from `Customer` models.

### Fixed
- Fixed a bug where a product’s `getCpEditUrl()` method could omit the site handle on multi-site installs. ([craftcms/cms#3089](https://github.com/craftcms/cms/issues/3089))
- Fixed a bug where handles and names for archived gateways were not freed up for re-use. ([#485](https://github.com/craftcms/commerce/issues/485))

## 1.2.1368 - 2018-11-30

### Changed
- Updated the Payflow Omnipay driver to 2.3.1
- Updated the Securepay Omnipay driver to 2.2.0
- Updated the Authorize.net Omnipay driver to 2.5.1
- Updated the Payment Express Omnipay driver to 2.2.1
- Updated the Eway Omnipay driver to 2.2.2
- Updated the Payfast Omnipay driver to 2.2

## 1.2.1366 - 2018-11-28

### Fixed
- Fixed a bug where it was possible to create duplicate order history change records.
- Fixed a bug where offsite gateways wouldn’t redirect back and complete the transaction correctly for Control Panel payments.

## 1.2.1365 - 2018-10-23

### Fixed
- Fix a bug where it wasn’t possible to set the billing address based off an existing shipping address.

### Fixed
- Fixed a Javascript error when viewing a customer field on the Edit User page.

## 1.2.1364 - 2018-08-23

### Fixed
- Fixed a PHP error that would occur when saving a User.

## 1.2.1363 - 2018-08-23

### Added
- Added the `resaveAllCustomerOrdersOnCustomerSave` config setting.

### Fixed
- Fixed a bug where the Date Paid column on the Orders index page could show incorrect values.

### Security
- Fixed a bug where it was possible to access purchase receipts when it shouldn’t have been.

## 1.2.1362 - 2018-05-10

### Changed
- Craft Commerce will now enforce boolean types for settings that a gateway expects to be boolean.

### Fixed
- Fixed an SSL error that could when communicating with the Authorize.net payment gateway.

## 1.2.1360 - 2018-03-23

### Added
- The order index page now includes the time when displaying order dates.

### Changed
- Line item modals on View Order pages now include the line item total.
- Added Craft 2.6.3013 compatibility.

## 1.2.1359 - 2018-03-08

### Fixed
- Fixed an error where variants would indicate they had zero stock at checkout when they had been marked as having unlimited stock.

## 1.2.1358 - 2018-03-07

### Fixed
- Fixed a PHP error that would occur when using an order element criteria model.

## 1.2.1356 - 2018-03-07

### Added
- Added the `shippingMethod` order criteria param.

### Changed
- Order recalculation now occurs after the `orders.onBeforeSaveOrder` event.

### Fixed
- Fixed a bug where a blank order could be placed if the cart’s cookie was deleted while the customer was on the payment page.
- Fixed a bug where a cart could be completed despite a lack of available stock, in some cases.
- Fixed a bug where the “Capture” transaction button on View Order pages was still shown after a capture was completed.

## 1.2.1354 - 2018-02-06

### Added
- Craft Commerce now adds `Craft Commerce` to the `X-Powered-By` header on requests, unless disabled by the [sendPoweredByHeader](https://craftcms.com/docs/config-settings#sendPoweredByHeader) config setting.

### Changed
- Updated the Authorize.net driver to 2.5.1
- Updated the Worldpay Omnipay driver to 2.2.2
- Updated the PayPal Omnipay driver to 2.6.4
- Updated the Payflow Omnipay driver to 2.3
- Updated the Dompdf Package to 0.8.2

### Fixed
- Fixed an error that occurred when generating an order PDF.
- Fixed a PHP error that could occur if you edited a non-primary currency’s settings.

## 1.2.1353 - 2018-01-18

### Added
- Added the `requireShippingMethodSelectionAtCheckout` config setting.
- Added new user permissions to manage shipping and tax settings without needing to be an admin.

### Fixed
- Fixed an error that occurred when creating or editing a discount.
- Fixed an error that occurred when generating an order PDF.

## 1.2.1352 - 2018-01-16

### Added
- Added the ability to update the email address of a guest order from the Control Panel.
- Added the `commerce_defaultCartShippingAddress` and `commerce_defaultCartBillingAddress` plugin hooks.

## 1.2.1351 - 2017-10-31

### Added
- Added the `defaultSku` product criteria param.
- Added stock information to the Product index page.

### Fixed
- Fixed a bug where stock validation was off by one when different line item options were set for the same purchasable.
- Fixed a bug where custom adjusters supplied by plugins where not being sorted by priority before getting applied to the order.
- Fixed a bug where the `commerce/cart/updateCart` action was not returning the correct validation errors when an invalid shipping address was submitted along with the `sameAddress` param.

## 1.2.1350 - 2017-10-05

### Changed
- Order adjustments are now displayed in the order they were applied, rather than alphabetically.

### Fixed
- Fixed a bug where emails weren’t getting sent to customers.

## 1.2.1349 - 2017-09-29

### Added
- Added the `cp.commerce.product.edit.right-pane` template hook, enabling plugins to modify the right pane on Edit Product pages.
- Added the `pdfAllowRemoteImages` config setting, which can be set to `true` to allow external images to be loaded in PDF templates.

### Changed
- `Commerce_OrderModel::getEmail()` now always returns the associated user account’s email, if there is one.
- The error data returned for `commerce/customerAddresses/save` Ajax requests now include field handles as the error keys.
- `Commerce_CustomerModel::getEmail()` has now been deprecated. It will only return the email address of the associated user account’s email if there was one. Use `order.email` to get the email address of the order within templates.
- Updated the Dompdf package to 0.8.1.
- Updated the PayFast Omnipay driver to 2.1.3.

### Fixed
- Fixed an issue in the example templates where the “Use same address for billing” checkbox would remain checked when different addresses were previously selected.
- Fixed a tax calculation error that occurred when included tax was removed from a product’s price and subsequent additional taxes did not take the removed values into account.

## 1.2.1346 - 2017-07-24

### Added
- Added the `autoSetNewCartAddresses` config setting, which can be set to `false` to prevent Craft Commerce from automatically assigning the last-used billing and shipping addresses on new carts.

### Changed
- Updated the Migs Omnipay driver to 2.2.2
- Updated the Stripe Omnipay driver to 2.4.7

### Fixed
- Fixed an API authentication error when making payments using the Stripe gateway.
- Fixed a bug where the `commerce/payments/pay` action was still processing the payment even if the cart had errors placed on it by other plugins.
- Fixed a bug where `LineItemModel::onSale()` could sometimes return an incorrect response due to rounding errors.
- Fixed a PHP error that could occur if a purchasable invalidated a line item when it was being added to a new cart.
- Fixed an issue where credit card forms’ First/Last Name fields were getting overridden by billing addresses’ values for some gateways.
- Fixed a bug where adding to cart with invalid `options` params would pass stock validation.

## 1.2.1345 - 2017-06-26

### Added
- Percentage-based discounts now have the option to be applied to the item’s original price or its discounted price (if other discounts were already applied).

## Changed
- Ajax requests to `commerce/cart/*` actions will now get a `itemSubtotal` key in the response JSON.
- Updated the Omnipay Stripe driver to 2.4.6.
- Updated the Omnipay Payment Express driver to 2.2.1.
- Updated the Omnipay MultiSafePay driver to 2.3.6.
- Updated the Omnipay Worldpay driver to 2.2.1.

### Fixed
- Fixed a bug where email address limits on discounts were able to by circumvented if the customer changed the casing of the coupon code.
- Fixed a PHP error that occurred when viewing a cart in the Control Panel if no payment methods had been created yet.
- Fixed a bug where discounts based on user group were not being added/removed after the user logged in/out.
- Fixed a bug where variants’ sale prices were only getting rounded when at least one sale was applied.
- Fixed a bug where special characters in Tax and Shipping Category names could break some form inputs in the Control Panel.
- Fixed a validation error that occurred when saving two shipping rules with the same name.

## 1.2.1343 - 2017-06-09

### Added
- Added the `pdfPaperSize` config setting.
- Added the `pdfPaperOrientation` config setting.
- Added a new Stripe gateway setting that determines whether the `receipt_email` param should be sent in payment requests.
- Added the `commerce_transactions.onCreateTransaction` event, which enables plugins to modify a newly-created transaction model.

### Changed
- Updated the Buckeroo driver to 2.2.
- Updated the Stripe driver to 2.4.5.
- Enabled the Buckeroo Credit Card Gateway within the Buckeroo Omnipay driver.

## 1.2.1342 - 2017-05-24

### Added
- Added support for Worldpay’s new `v1` API.

### Fixed
- Fixed a bug where `VariantModel:onSale()` could sometimes return an incorrect response due to rounding errors.
- Fixed a PHP error that occurred when saving a product with an empty dimension input on servers running PHP 7.
- Fixed a issue where orders were getting recalculated after receiving a completion response, when using the Sage Pay gateway.
- Fixed a PHP error that occurred when a plugin prevented a purchasable from getting added to the cart.

## 1.2.1341 - 2017-05-02

### Changed
- Increased the tax rate decimal storage length to allow 3 decimal places in tax rate percentages.
- The `CommerceDbHelper` class has be deprecated.

### Fixed
- Fixed a bug where some characters in product names were getting double-encoded on View Order pages.
- Fixed a bug where orders were incorrectly recalculating their adjustments when receiving notifications from the SagePay payment gateway.
- Fixed a tax calculation bug that occurred when using the “Total Order Price” taxable subject.

## 1.2.1339 - 2017-04-24

### Added
- Added new “Taxable Subject” options to Tax Rates, enabling taxes to be applied at the order level.
- Added the `datePaid` order element criteria attribute.

### Changed
- Updated the Dompdf package to 0.8.
- Updated the Omnipay Mollie driver to 3.2.
- Updated the Omnipay Authorize.net driver to 2.5.
- Updated the Omnipay MultiSafePay driver to 2.3.4.

### Fixed
- Fixed some PHP errors that occurred when rendering PDFs on servers running PHP 7.1.

## 1.2.1338 - 2017-04-04

### Added
- Added the `requireBillingAddressAtCheckout` config setting.
- Added the `cp.commerce.order.main-pane` template hook to the View Order page.
- Added `Commerce_VariantModel::hasStock()`.

### Fixed
- Fixed some PHP errors that occurred when saving products on servers running PHP 7.1.
- Fixed a bug where the `commerce/payments/pay` action was not blocking disabled payment methods.
- Fixed a bug where old carts did not default to the primary payment currency when their current payment currency was no longer valid.

## 1.2.1337 - 2017-03-08

### Added
- Added the `commerce_sale.onBeforeMatchProductAndSale` event, which enables plugins to add custom matching logic to sales.
- Added the `commerce_products.onBeforeEditProduct` event.
- Added the `cp.commerce.product.edit` template hook to the Edit Product page.

### Changed
- If a product SKU can’t be generated from its product type’s Automatic SKU Format, Craft Commerce now logs why.

### Fixed
- Fixed some PHP errors that occurred on servers running PHP 7.1.
- Fixed a bug where line items could be removed if their `qty` param was missing from a `commerce/cart/updateLineItem` request.
- The Orders index page now displays zero-value currency amounts, instead of leaving the cell blank.
- Fixed bug where duplicate products could be displayed when editing sales when the User Groups condition was in use.
- Fixed a bug where the `isUnpaid` and `isPaid` order element criteria params did not work correctly.
- Fixed a PHP error that occurred if a plugin’s custom shipping method object didn’t inherit `BaseModel`.
- Fixed a bug where payments made with MultiSafepay would be marked as successful before the user was redirected to the offsite gateway.
- Fixed a bug where shipping rule names were required to be unique across the entire installation, rather than per-shipping method.

## 1.2.1334 - 2017-01-30

### Added
- Added a new `purgeInactiveCarts` config setting, which determines whether Craft Commerce should purge inactive carts from the database (`true` by default).
- Added a new `commerce_modifyOrderAdjusters` hook, which enables plugins to modify the order adjusters before they are applied.
- Added the “Shipping Method” and “Payment Method” table attribute options to the Orders index page.

### Changed
- Updated the Stripe gateway library to 2.4.2.
- Updated the PayPal gateway library to 2.6.3.
- Fixed a memory error that occurred when purging a large number of carts.

### Fixed
- Fixed a bug where the `hasVariant` product criteria attribute would only account the first 100 variants.
- Fixed a bug where custom order adjusters could not inspect earlier adjustments made to the order within the current recalculation.
- Fixed a bug where the default product type that gets created on installation was referencing the old `commerce` templates path, rather than `shop`.
- Fixed compatibility with some payment gateways that were expecting abbreviated state names in the billing address.

## 1.2.1333 - 2017-01-05

### Fixed
- Fixed a PHP error that occurred when retrieving the sale price of variants that were fetched via `craft.commerce.products`.

## 1.2.1332 - 2017-01-03

### Added
- Added the `commerce_modifyItemBag` hook, allowing plugins to modify cart information sent to the payment gateway.
- Added the `requireShippingAddressAtCheckout` config setting.
- Added a new `defaultHeight` product criteria param, for querying products by their default variant’s height.
- Added a new `defaultLength` product criteria param, for querying products by their default variant’s length.
- Added a new `defaultWidth` product criteria param, for querying products by their default variant’s width.
- Added a new `defaultWeight` product criteria param, for querying products by their default variant’s weight.

### Fixed
- Fixed a bug where sales were not being applied to variants that were fetched via `craft.commerce.variants`.
- Fixed a bug where line items’ `salePrice` were not reflecting any changes made to their `saleAmount` via the `lineItem.onPopulateLineItem` event.

## 1.2.1331 - 2016-12-13

### Added
- Craft Commerce now includes a gateway adapter for Payeezy by First Data.
- Added `Commerce_VariantModel::getSalesApplied()`, which returns an array of the `Commerce_SaleModel` objects that were used to calculate the salePrice of the variant.

### Changed
- Ajax requests to `commerce/cart/*` actions now include `subtotal` and `shippingCategoryId` properties in the response data.
- The `commerce_orders/beforeOrderComplete` event now gets fired a little later than before, giving plugins a chance to change the order status ID.

### Fixed
- Fixed a bug where MultiSafepay was not being treated as an offsite payment gateway.

## 1.2.1330 - 2016-12-06

### Changed
- Added a new `baseTax` attribute to order models, which can be modified by custom order adjusters to add taxes to the order as a whole.
- `Commerce_OrderModel::getTotalTax()` now includes the new `baseTax` amount.

### Fixed
- Fixed a rounding error that occurred with some percentage-based discounts.
- Fixed a PHP error that occurred when searching for products with the `hasVariants` criteria param, in some cases.

## 1.2.1329 - 2016-11-30

### Fixed
- Fixed a bug where discounts without a coupon code condition could apply before their start date.
- Fixed a bug where the `hasSales` product criteria attribute would only apply to the first 100 products.
- Fixed a bug where the post-payment redirect would take the customer to the site homepage.

## 1.2.1328 - 2016-11-29

### Added
- Craft Commerce now includes a gateway adapter for MultiSafepay.

### Changed
- Ajax requests to `cart/updateCart` now include a `cart` object in the response data in the event of an error.

### Fixed
- Fixed a bug where PayPal payments could fail due to inconsistencies between how Craft Commerce and PayPal calculated the total payment amount for transactions.
- Fixed a bug where First Name and Last Name customer field labels weren’t being translated for the current locale in the Control Panel.
- Fixed a bug some offsite gateway payment requests were not getting sent with the correct return and cancel URLs.
- Fixed a bug that prevented Craft Commerce from updating successfully from pre-1.0 versions on case-sensitive file systems.
- Fixed a bug where applicable VAT taxes were not being removed correctly for customers with a valid VAT ID.
- Fixed a bug where archived payment methods were still showing up as options in Control Panel payment form modals.

## 1.2.1327 - 2016-10-25

### Changed
- When saving a product type, if any tax/shipping categories had been deselected, Craft Commerce will now reassign any existing products with the no-longer-available tax/shipping categories to the default categories.
- The “HTML Email Template Path” Email setting can now contain Twig code.

### Fixed
- Fixed a bug where Craft Commerce was not respecting the system time zone when purging inactive carts.
- Fixed a bug where a no-longer-applicable shipping method could still be selected by a cart if it was the only defined shipping method.
- Fixed a bug where the `Commerce_ProductModel` provided by the onSaveProduct event was not updated with the latest and greatest values based on its default variant.
- Fixed a bug where all products were being re-saved when a product type was saved, rather than just the products that belong to that product type.
- Fixed a PHP error that occurred when adding something to the cart, if the cart didn’t have a shipping address yet and the default tax zone’s tax rate was marked as VAT.
- Fixed a bug where a coupon based discount could apply before its start date.

## 1.2.1325 - 2016-10-13

### Fixed
- Fixed a PHP error that occurred when a custom purchasable didn’t provide a tax category ID.
- Fixed a bug where the relevant template caches were not being cleared after the stock of a variant was deducted.
- Fixed a display issue on the order transaction details modal when a large amount of gateway response data was present.

## 1.2.1324 - 2016-10-12

### Fixed
- Fixed a bug where orders were not being marked as complete after successful offsite gateway payments.
- Fixed a PHP error that occurred when deleting a product type.

## 1.2.1323 - 2016-10-11

### Added
- It’s now possible to accept payments in multiple currencies.
- Added Shipping Categories.
- Discounts can now be user-sorted, which defines the order that they will be applied to carts.
- Discounts now have the option to prevent subsequent discounts from being applied.
- The start/end dates for Discounts and Sales can now specify the time of day.
- Discounts can now have a “Minimum Purchase Quantity” condition.
- Product Types now have an “Order Description Format” setting, which can be used to override the description of the products in orders’ line items.
- Addresses now have “Attention”, “Title”, and “Business ID” fields.
- Added the “Order PDF Filename Format” setting in Commerce → Settings → General Settings, for customizing the format of order PDF filenames.
- Added the `useBillingAddressForTax` config setting. If enabled, Craft Commerce will calculate taxes based on orders’ billing addresses, rather than their shipping addresses.
- Added the `requireEmailForAnonymousPayments` config setting. If enabled, Craft Commerce will require the email address of the order to be submitted in anonymous payment requests.
- The IP address of the customer is now stored on the order during order completion.
- Craft Commerce now makes all payment gateways available to unregistered installs, rather than limiting users to a single “Dummy” gateway.
- Added support for SagePay Server.
- Added support for the Netbanx Hosted.
- Added the `commerceCurrency` filter, which works identically to the |currency filter by default, but also has `convert` and `format` arguments that can be used to alter the behavior.
- Added `craft.commerce.shippingMethods`.
- Added `craft.commerce.shippingCategories`.
- Added `craft.commerce.shippingZones`.
- Added `craft.commerce.taxZones`.
- Added `OrderStatusService::getDefaultOrderStatusId()`.
- Added the `commerce_payments.onBeforeCaptureTransaction` and `onCaptureTransaction` events.
- Added the `commerce_payments.onBeforeRefundTransaction` and `onRefundTransaction` events.
- Added the `commerce_email.onBeforeSendEmail` and `onSendEmail` events.
- Added the `cp.commerce.order.edit` hook to the View Order page template.
- Added the [PHP Units of Measure](https://github.com/PhpUnitsOfMeasure/php-units-of-measure) PHP package.
- Added the [Vat Validation](https://github.com/snowcap/vat-validation) PHP package.

### Changed
- The tax categories returned by the template function `craft.commerce.getTaxCategories()` are now represented by `Commerce_TaxCategory` models by default, rather than arrays. To get them returned as arrays, you can pass `true` into the function.
- Status-change notification emails are now sent to the customer in the language they placed the order with.
- It’s now possible to update product statuses on the Products index page.
- The example templates folder has been renamed from “commerce” to “shop”.
- Craft Commerce now re-saves existing products when a Product Type’s settings are saved.
- The Tax Rates index page now lists the Tax Categories and Tax Zones each Tax Rate uses.
- Tax Rates now have the option to exclude themselves from orders with a valid VAT ID.
- Transaction Info HUDs on View Order pages now show the transaction IDs.
- Craft Commerce now stores the complete response data for gateway transaction requests in the commerce_transactions table.
- The commerce/cart/updateCart action now includes all validation errors found during partial cart updates in its response.
- Reduced the number of order recalculations performed during payment.
- The View Order page no longer labels an order as paid if its total price is zero.
- Craft Commerce now logs invalid email addresses when attempting to send order status notification emails.
- Custom fields on an order can now only be updated during payment if it is the user’s active cart.
- Craft Commerce now provides Stripe with the customer’s email address to support Stripe’s receipt email feature.
- Payment failures using PayPal Express now redirect the customer back to PayPal automatically, rather than displaying a message instructing the customer to return to PayPal.
- Updated the Authorize.Net gateway library to 2.4.2.
- Updated the Dummy gateway library to 2.1.2.
- Updated the Molli gateway library to 3.1.
- Updated the Payfast gateway library to 2.1.2.
- Updated the Payflow gateway library to 2.2.1.
- Updated the Stripe gateway library to 2.4.1.

### Deprecated
- Deprecated the `update` variable in email templates. The `orderHistory` variable should be used instead.

### Fixed
- Fixed a bug where `Commerce_OrderService::completeOrder()` was not checking to make sure the order was not already completed before doing its thing.
- Fixed a bug where addresses’ “Map” links on View Order pages were not passing the full address to the Google Maps window.
- Fixed an bug where address validation was not respecting the country setting, “Require a state to be selected when this country is chosen”.
- Fixed a bug where submitting new addresses to a fresh cart caused a cart update failure.
- Fixed a bug where collapsed variants’ summary info was overlapping the “Default” button.

## 1.1.1317 - 2016-09-27

### Added
- Craft Commerce is now translated into Portuguese.

### Fixed
- Fixed a bug where Edit Address modals on View Order pages were not including custom states in the State field options.

## 1.1.1217 - 2016-08-25

### Fixed
- Fixed a PHP error that occurred when referencing the default currency.

## 1.1.1216 - 2016-08-25

### Fixed
- Fixed a bug where eager-loading product variants wasn’t working.
- Fixed a bug where customer addresses were not showing up in the View Order page if they contained certain characters.
- Fixed a bug where orders were not getting marked as complete when they should have in some cases, due to a rounding comparison issue.

## 1.1.1215 - 2016-08-08

### Changed
- Customer Info fields now return the user’s `CustomerModel` when accessed in a template.

### Fixed
- Fixed a bug where discounts that apply free shipping to an order were not including the shipping reduction amount in the discount order adjustment amount.
- Fixed a bug where editing an address in the address book would unintentionally select that address as the active cart’s shipping address.
- Fixed SagePay Server gateway support.

## 1.1.1214 - 2016-07-20

### Fixed
- Fixed an error that occurred when PayPal rejected a payment completion request due to duplicate counting of included taxes.
- Fixed a MySQL error that could occur when `ElementsService::getTotalElements()` was called for orders, products, or variants.

## 1.1.1213 - 2016-07-05

### Changed
- Transaction dates are now shown on the View Order page.
- Order status change dates are now shown on the View Order page.
- Updated the Authorize.Net Omnipay gateway to 2.4, fixing issues with Authorize.Net support.
- Cart item information is now sent on gateway payment completion requests, in addition to initial payment requests.

### Fixed
- Fixed a bug where payments using Worldpay were not getting automatically redirected back to the store.

## 1.1.1212 - 2016-06-21

### Changed
- Line item detail HUDs within the View Order page now include the items’ subtotals.
- Renamed `Commerce_LineItemModel`’s `subtotalWithSale` attribute to `subtotal`, deprecating the former.
- Renamed `Commerce_OrderModel`’s `itemSubtotalWithSale` attribute to `itemSubtotal`, deprecating the former.
- Each of the nested arrays returned by `craft.commerce.availableShippingMethods` now include a `method` key that holds the actual shipping method object.

### Fixed
- Fixed a MySQL error that occurred when MySQL was running in Strict Mode.
- Fixed a rounding error that occurred when calculating tax on shipping costs.

## 1.1.1211 - 2016-06-07

### Added
- Added a new “Per Email Address Limit” condition to coupon-based discounts, which will limit the coupons’ use by email address.
- Added the ability to clear usage counters for coupon-based discounts.
- Added a new `hasSales` product criteria param, which can be used to limit the resulting products to those that have at least one applicable sale.
- Added a new `hasPurchasables` order criteria param, which can be used to limit the resulting orders to those that contain specific purchasables.
- Added a new `commerce_lineItems.onPopulateLineItem` event which is called right after a line item has been populated with a purchasable, and can be used to modify the line item attributes, such as its price.
- Added `LineItemModel::getSubtotal()` as an alias of the now-deprecated `getSubtotalWithSale()`.

### Fixed
- Fixed a bug where the “Per User Limit” discount condition was not being enforced for anonymous users.
- Fixed a bug where the quantity was not being taken into account when calculating a weight-based shipping cost.
- Fixed a validation error that could occur when submitting a payment for an order with a percentage-based discount.
- Fixed a bug where the cart was not getting recalculated when an associated address was updated in the user’s address book.

## 1.1.1210 - 2016-05-17

### Fixed
- Fixed a bug where sales could be applied to the same line item more than once.
- Fixed a bug where the `commerce/cart/cartUpdate` controller action’s Ajax response did not have up-to-date information.

## 1.1.1208 - 2016-05-16

### Added
- Added `commerce_products.onBeforeDeleteProduct` and `onDeleteProduct` events.

### Fixed
- Fixed a PHP error that occurred when adding a new item to the cart.

## 1.1.1207 - 2016-05-11

### Fixed
- Fixed a PHP error that occurred when saving a product with unlimited stock.

## 1.1.1206 - 2016-05-11

### Changed
- It’s now possible to show customers’ and companies’ names on the Orders index page.
- Craft Commerce now sends customers’ full names to the payment gateways, pulled from the billing address.
- Craft Commerce now ensures that orders’ prices don’t change in the middle of payment requests, and declines any payments where the price does change.
- The onBeforeSaveProduct event is now triggered earlier to allow more modification of the product model before saving.
- Updated the Omnipay gateway libraries to their latest versions.

### Fixed
- Fixed a bug where changes to purchasable prices were not reflected in active carts.
- Fixed a PHP error that occurred when an active cart contained a variant that had no stock or had been disabled.
- Fixed a PHP error that occurred when paying with the Paypal Express gateway.

## 1.1.1202 - 2016-05-03

### Added
- Added the `commerce_lineItems.onCreateLineItem` event.
- Added the `hasStock` variant criteria param, which can be set to `true` to find variants that have stock (including variants with unlimited stock).

### Changed
- The View Order page now shows whether a coupon code was used on the order.
- All payment gateways support payments on the View Order page now.
- It’s now possible to delete countries that are in use by tax/shipping zones and customer addresses.
- State-based tax/shipping zones now can match on the state abbreviation, in addition to the state name/ID.
- Craft Commerce now sends descriptions of the line items to gateways along with other cart info, when the `sendCartInfoToGateways` config setting is enabled.

### Fixed
- Fixed a bug where payment method setting values that were set from config/commerce.php would get saved to the database when the payment method was resaved in the Control Panel.
- Fixed a PHP error that occurred when calling `Commerce_OrderStatusesService::getAllEmailsByOrderStatusId()` if the order status ID was invalid.
- Fixed a PHP error that occurred when a cart contained a disabled purchasable.
- Fixed a bug where an order status’ sort order was forgotten when it was resaved.
- Fixed a bug where the `hasVariant` product criteria param was only checking the first 100 variants.
- Fixed a bug where only logged-in users could view a tokenized product preview URL.
- Fixed an issue where the selected shipping method was not getting removed from the cart when it was no longer available, in some cases.

## 1.1.1200 - 2016-04-13

### Added
- Added the `commerce_products.onBeforeSaveProduct` and `onSaveProduct` events.
- Added the `commerce_lineItems.onBeforeSaveLineItem` and `onSaveLineItem` events.

### Changed
- Stock fields are now marked as required to make it more clear that they are.
- Added a new “The Fleece Awakens” default product.

### Fixed
- Fixed an error that occurred when a variant was saved without a price.
- Fixed a bug where various front-end templates wouldn’t load correctly from the Control Panel if the [defaultTemplateFileExtensions](link) or [indexTemplateFilename](link) config settings had custom values.
- Fixed a bug where products’ `defaultVariantId` property was not being set on first save.
- Fixed a validation error that occurred when a cart was saved with a new shipping address and an existing billing address.
- Fixed a bug where customers’ last-used billing addresses were not being remembered.
- Fixed a MySQL error that occurred when attempting to delete a user that had an order transaction history.

### Security
- Fixed an XSS vulnerability.

## 1.1.1198 - 2016-03-22

### Added
- Added the `sendCartInfoToGateways` config setting, which defines whether Craft Commerce should send info about a cart’s line items and adjustments when sending payment requests to gateways.
- Product models now have a `totalStock` property, which returns the sum of all available stock across all of a product’s variants.
- Product models now have an `unlimitedStock` property, which returns whether any of a product’s variants have unlimited stock.
- Added the `commerce_variants.onOrderVariant` event.

### Changed
- Updated the Omnipay Authorize.Net driver to 2.3.1.
- Updated the Omnipay FirstData driver to 2.3.0.
- Updated the Omnipay Mollie driver to 3.0.5.
- Updated the Omnipay MultiSafePay driver to 2.3.0.
- Updated the Omnipay PayPal driver to 2.5.3.
- Updated the Omnipay Pin driver to 2.2.1.
- Updated the Omnipay SagePay driver to 2.3.1.
- Updated the Omnipay Stripe driver to  v2.3.1.
- Updated the Omnipay WorldPay driver to 2.2.

### Fixed
- Fixed a bug where shipping address rules and tax rates were not finding their matching shipping zone in some cases.
- Fixed a bug where the credit card number validator was not removing non-numeric characters.
- Fixed a PHP error that occurred when saving an order from a console command.

## 1.1.1197 - 2016-03-09

### Changed
- Ajax requests to the “commerce/payments/pay” controller action now include validation errors in the response, if any.

### Fixed
- Fixed a credit card validation bug that occurred when using the eWay Rapid gateway.
- Fixed an error that occurred on the Orders index page when searching for orders.
- Fixed a bug where refreshing the browser window after refunding or paying for an order on the View Order page would attempt to re-submit the refund/payment request.
- Fixed a bug where `Commerce_PaymentsService::processPayment()` was returning `false` when the order was already paid in full (e.g. due to a 100%-off coupon code).
- Fixed a bug where variants were defaulting to disabled for products that only had a single variant.

## 1.1.1196 - 2016-03-08

### Added
- Added Slovak message translations.
- Added Shipping Zones, making it easier to relate multiple Shipping Methods/Rules to a common list of countries/states. (Existing Shipping Rules will be migrated to use Shipping Zones automatically.)
- Added a “Recent Orders” Dashboard widget that shows a table of recently-placed orders.
- Added a “Revenue” Dashboard widget that shows a chart of recent revenue history.
- The Orders index page now shows a big, beautiful revenue chart above the order listing.
- It’s now possible to edit Billing and Shipping addresses on the View Order page.
- It’s now possible to manually mark orders as complete on the View Order page.
- It’s now possible to submit new order payments from the View Order page.
- Edit Product pages now have a “Save as a new product” option in the Save button menu.
- Edit Product pages now list any sales that are associated with the product.
- It’s now possible to sort custom order statuses.
- It’s now possible to sort custom payment methods.
- It’s now possible to soft-delete payment methods.
- Added a “Link to a product” option to Rich Text fields’ Link menus, making it easy to create links to products.
- Added support for Omnipay “item bags”, giving gateways some information about the cart contents.
- Added the “gatewayPostRedirectTemplate” config setting, which can be used to specify the template that should be used to render the POST redirection page for gateways that require it.
- Added support for eager-loading variants when querying products, by setting the `with: 'variants'` product param.
- Added support for eager-loading products when querying variants, by setting the `with: 'product'` variant param.
- Added `craft.commerce.variants` for querying product variants with custom parameters.
- Added the “defaultPrice” product criteria parameter, for querying products by their default variant’s price.
- Added the “hasVariant” product criteria parameter, for querying products that have a variant matching a specific criteria. (This replaces the now-deprecated “withVariant” parameter”.)
- Added the “stock” variant criteria parameter, for querying variants by their available stock.
- Added the “commerce/payments/pay” controller action, replacing the now-deprecated “commerce/cartPayment/pay” action.
- Added the “commerce/payments/completePayment” controller action, replacing the now-deprecated “commerce/cartPayment/completePayment” action.
- The “commerce/payments/pay” controller action now accepts an optional “orderNumber” param, for specifying which order should receive the payment. (If none is provided, the active cart is used.)
- The “commerce/payments/pay” controller action now accepts an optional “expiry” parameter, which takes a combined month + year value in the format “MM/YYYY”.
- The “commerce/payments/pay” controller action doesn’t required “redirect” and “cancelUrl” params, like its predecessor did.
- The “commerce/payments/pay” controller action supports Ajax requests.
- Added an abstract Purchasable class that purchasables can extend, if they want to.
- Gateway adapters are now responsible for creating the payment form model themselves, via the new `getPaymentFormModel()` method.
- Gateway adapters are now responsible for populating the CreditCard object based on payment form data themselves, via the new `populateCard()` method.
- Gateway adapters now have an opportunity to modify the Omnipay payment request, via the new `populateRequest()` method.
- Gateway adapters can now add support for Control Panel payments by implementing `cpPaymentsEnabled()` and `getPaymentFormHtml()`.

### Changed
- `Commerce_PaymentFormModel` has been replaced by an abstract BasePaymentFormModel class and subclasses that target specific gateway types.
- Gateway adapters must now implement the new `getPaymentFormModel()` and `populateCard()` methods, or extend `CreditCardGatewayAdapter`.
- The signatures and behaviors of `Commerce_PaymentsService::processPayment()` and `completePayment()` have changed.
- New Sales and Discounts are now enabled by default.
- The Orders index page now displays orders in chronological order by default.
- It is no longer possible to save a product with a disabled default variant.
- It is no longer possible to add a disabled variant, or the variant of a disabled product, to the cart.
- `Commerce_PaymentsService::processPayment()` and `completePayment()` no longer respond to the request directly, unless the gateway requires a redirect via POST. They now return `true` or `false` indicating whether the operation was successful, and leave it up to the controller to handle the client response.

### Deprecated
- The `commerce/cartPayment/pay` action has been deprecated. `commerce/payments/pay` should be used instead.
- The `commerce/cartPayment/completePayment` action has been deprecated. `commerce/payments/completePayment` should be used instead.
- The `withVariant` product criteria parameter has been deprecated. `hasVariant` should be used instead.

## 1.0.1190 - 2016-02-26

### Fixed
- Fixed a bug where product-specific sales were not being applied correctly.

## 1.0.1189 - 2016-02-23

### Changed
- Reduced the number of SQL queries required to perform various actions.
- The “Enabled” checkbox is now checked by default when creating new promotions and payment methods.
- Edit Product page URLs no longer require the slug to be appended after the product ID.
- Completed orders are now sorted by Date Ordered by default, and incomplete orders by Date Updated, in the Control Panel.

### Fixed
- Fixed a PHP error that occurred if an active cart contained a purchasable that had been deleted in the back-end.
- Fixed a PHP error that occurred when trying to access the addresses of a non-existent customer.
- Fixed a bug where only a single sale was being applied to products even if there were multiple matching sales.

## 1.0.1188 - 2016-02-09

### Changed
- Order queries will now return zero results if the `number` criteria param is set to any empty value besides `null` (e.g. `false` or `0`).
- Improved the behavior of the Status menu in the Update Order Status modal on View Order pages.
- Added some `<body>` classes to some of Craft Commerce’s Control Panel pages.

### Fixed
- Fixed a bug where new carts could be created with an existing order number.
- Fixed a bug where the default descriptions given to discounts were not necessarily using the correct currency and number formats.
- Fixed a bug where a default state was getting selected when creating a new shipping rule, but it was not getting saved.
- Fixed a bug where variants could not be saved as disabled.

## 1.0.1187 - 2016-01-28

### Added
- Added `craft.commerce.getDiscountByCode()`, making it possible for templates to fetch info about a discount by its code.

### Changed
- OrderHistoryModel objects now have a `dateCreated` attribute.

### Fixed
- Fixed a bug where customers could select addresses that did not belong to them.
- Fixed a bug where new billing addresses were not getting saved properly when carts were set to use an existing shipping address, but `sameAddress` was left unchecked.
- Fixed a bug where numeric variant fields (e.g Price) were storing incorrect values when entered from locales that use periods as the grouping symbol.
- Fixed a PHP error that occurred when saving a custom order status with no emails selected.
- Fixed a bug where discounts were being applied to carts even after the discount had been disabled.
- Fixed a bug where carts were not displaying descriptions for applied discounts.
- Fixed a bug where variants’ Title fields were not showing the correct locale ID in some cases.

## 1.0.1186 - 2016-01-06

### Changed
- Updated the translation strings.

### Fixed
- Fixed a PHP error that occurred when attempting to change a tax category’s handle.
- Fixed a PHP error that occurred when attempting to save a discount or sale without selecting any products or product types.

## 1.0.1185 - 2015-12-21

### Added
- Orders now have an `email` criteria parameter which can be used to only query orders placed with the given email.
- Address objects now have `getFullName()` method, for returning the customer’s first and last name combined.
- Added the `totalLength` attribute to front-end cart Ajax responses.
- It’s now possible to sort orders by Date Ordered and Date Paid on the Orders index page.

### Changed
- A clear error message is now displayed when attempting to save a product, if the product type’s Title Format setting is invalid.
- A clear error message is now displayed when attempting to save a product, if the product type’s Automatic SKU Format setting is invalid.
- Any Twig errors that occur when rendering email templates are now caught and logged, without affecting the actual order status change.
- The Payment Methods index now shows the payment methods’ gateways’ actual display names, rather than their class names.
- Payment method settings that are being overridden in craft/config/commerce.php now get disabled from Edit Payment Method pages.
- The extended line item info HUD now displays the included tax for the line item.

### Fixed
- Fixed a bug where the cart was not immediately forgotten when an order was completed.
- Fixed a bug where `Commerce_OrderModel::getTotalLength()` was returning the total height of each of its line items, rather than the length.
- Fixed a bug where variants’ height, length, and width were not being saved correctly on order line item snapshots.
- Fixed a bug where order queries would return results even when the `user` or `customer` params were set to invalid values.
- Fixed a PHP error that occurred when accessing a third party shipping method from an order object.
- Fixed a PHP error that occurred when accessing the Sales index page.
- Fixed a PHP error that occurred when loading dependencies on some servers.
- Fixed a JavaScript error that occurred when viewing extended info about an order’s line items.
- Fixed some language and styling bugs.

## 1.0.1184 - 2015-12-09

### Added
- Added support for inline product creation from product selection modals.
- Products now have an `editable` criteria parameter which can be used to only query products which the current user has permission to edit.
- Added support for payment methods using the eWAY Rapid gateway.

### Changed
- Improved compatibility with some payment gateways.
- Added the `shippingMethodId` attribute to front-end cart Ajax responses.
- Users that have permission to access Craft Commerce in the Control Panel, but not permission to manage Orders, Products, or Promotions now get a 403 error when accessing /admin/commerce, rather than a blank page.
- The “Download PDF” button no longer appears on the View Order page if no PDF template exists yet.
- `Commerce_OrderModel::getPdfUrl()` now only returns a URL if the PDF template exists; otherwise null will be returned.
- Errors that occur when parsing email templates now get logged in craft/storage/runtime/logs/commerce.log.
- Improved the wording of error messages that occur when an unsupported gateway request is made.

### Fixed
- Fixed a bug where entering a sale’s discount amount to a decimal number less than 1 would result in the sale applying a negative discount (surcharge) to applicable product prices. Please check any existing sales to make sure the correct amount is being discounted.
- Fixed bug where email template errors would cause order completion to fail.
- Fixed a bug where shipping rule description fields were not being saved.
- Fixed a PHP error that could occur when saving a product via an Element Editor HUD.
- Fixed a bug where billing and shipping addresses were receiving duplicate validation errors when the `sameAddress` flag was set to true.
- Fixed a JavaScript error that occurred when changing an order’s status on servers with case-sensitive file systems.

## 1.0.1183 - 2015-12-03

### Changed
- Discounts are now entered as positive numbers in the CP (e.g. a 50% discount is defined as either “0.5” or “50%” rather than “-0.5” or “-50%”).
- Added the `commerce_cart.onBeforeAddToCart` event.
- Added the `commerce_discounts.onBeforeMatchLineItem` event, making it possible for plugins to perform additional checks when determining if a discount should be applied to a line item.
- Added the `commerce_payments.onBeforeGatewayRequestSend` event.

### Fixed
- Fixed a PHP error that would occur when the Payment Methods index page if any of the existing payment methods were using classes that could not be found.
- Fixed a bug where some failed payment requests were not returning an error message.
- Fixed a bug where `PaymentsService::processPayment()` was attempting to redirect to the order’s return URL even if it didn’t have one, in the event that the order was already paid in full before `processPayment()` was called. Now `true` is returned instead.
- Fixed some UI strings that were not getting properly translated.

## 1.0.1182 - 2015-12-01

### Added
- Tax Rates now have a “Taxable Subject” setting, allowing admins to choose whether the Tax Rate should be applied to shipping costs, price, or both.
- View Order pages now display notes and options associated with line items.
- Added new `commerce_addresses.beforeSaveAddress` and `saveAddress` events.
- Purchasables now must implement a `getIsPromotable()` method, which returns whether the purchasable can be subject to discounts.
- Variants now support a `default` element criteria param, for only querying variants that are/aren’t the default variant of an invariable product.

### Changed
- All number fields now display values in the current locale’s number format.
- Variant descriptions now include the product’s title for products that have variants.
- It’s now more obvious in the UI that you are unable to delete an order status while orders exist with that status.
- The `commerce_orders.beforeSaveOrder` event now respects event’s `$peformAction` value.
- The `commerce_orders.beforeSaveOrder` and `saveOrder` events trigger for carts, in addition to completed orders.
- `Commerce_PaymentsService::processPayment()` no longer redirects the browser if the `$redirect` argument passed to it is `null`.
- Renamed `Commerce_VariantsService::getPrimaryVariantByProductId()` to `getDefaultVariantByProductId()`.
- Updated all instances of `craft.commerce.getCart()` to `craft.commerce.cart` in the example templates.
- Customers are now redirected to the main products page when attempting to view their cart while it is empty.

### Removed
- Removed the `commerceDecimal` and `commerceCurrency` template filters. Craft CMS’s built-in [number](https://craftcms.com/docs/templating/filters#number) and [currency](https://craftcms.com/docs/templating/filters#currency) filters should be used instead. Note that you will need to explicitly pass in the cart’s currency to the `currency` filter (e.g. `|currency(craft.commerce.cart.currency)`).

### Fixed
- Fixed a bug where View Order pages were displaying links to purchased products even if the product didn’t exist anymore, which would result in a 404 error.
- Fixed a bug where orders’ base shipping costs and base discounts were not getting reset when adjustments were recalculated.
- Fixed the “Country” and “State” field labels on Edit Shipping Rule pages, which were incorrectly pluralized.
- Fixed a bug where toggling a product/variant’s “Unlimited” checkbox was not enabling/disabling the Stock text input.
- Fixed a PHP error that occurred on order completion when purchasing a third party purchasable.
- Fixed a PHP error that occurred when attempting to add a line item to the cart with zero quantity.
- Fixed a bug where the state name was not getting included from address models’ `getStateText()` methods.
- Fixed a PHP error that would occur when saving a variable product without any variants.

## 0.9.1179 - 2015-11-24

### Added
- Added a new “Manage orders” user permission, which determines whether the current user is allowed to manage orders.
- Added a new “Manage promotions” user permission, which determines whether the current user is allowed to manage promotions.
- Added new “Manage _[type]_ products” user permissions for each product type, which determines whether the current user is allowed to manage products of that type.
- It’s now possible to set payment method settings from craft/config/commerce.php. To do so, have the file return an array with a `'paymentMethodSettings'` key, set to a sub-array that is indexed by payment method IDs, whose sub-values are set to the payment method’s settings (e.g. `return ['paymentMethodSettings' => ['1' => ['apiKey' => getenv('STRIPE_API_KEY')]]];`).
- Added an `isGuest()` method to order models, which returns whether the order is being made by a guest account.
- The `cartPayment/pay` controller action now checks for a `paymentMethodId` param, making it possible to select a payment gateway at the exact time of payment.
- Added `Commerce_TaxCategoriesService::getTaxCategoryByHandle()`.

### Changed
- Ajax requests to `commerce/cart/*` controller actions now get the `totalIncludedTax` amount in the response.
- Renamed `Commerce_ProductTypeService::save()` to `saveProductType()`.
- Renamed `Commerce_PurchasableService` to `Commerce_PurchasablesService` (plural).
- Renamed all `Commerce_OrderStatusService` methods to be more explicit (e.g. `save()` is now `saveOrderStatus()`).
- Renamed `Commerce_TaxCategoriesService::getAll()` to `getAllTaxCategories()`.
- Added “TYPE_” and “STATUS_” prefixes to each of the constants on TransactionRecord, to clarify their purposes.
- Order models no longer have $billingAddressData and $shippingAddressData properties. The billing/shipping addresses chosen by the customer during checkout are now duplicated in the craft_commerce_addresses table upon order completion, and the order’s billingAddressId and shippingAddressId attributes are updated to the new address records’ IDs.
- Purchasables must now have a `getTaxCategoryId()` method, which returns the ID of the tax category that should be applied to the purchasable.
- Third-party purchasables can now have taxes applied to their line items when in the cart.
- Added `totalTax`, `totalTaxIncluded`, `totalDiscount`, and `totalShippingCost` to the example templates’ order totals info.

### Fixed
- Fixed a bug where variants were not being returned in the user-defined order on the front end.
- Fixed a bug where `Commerce_OrdersService::getOrdersByCustomer()` was returning incomplete carts. It now only returns completed orders.
- Fixed a bug where the line items’ `taxIncluded` amount was not getting reset to zero before recalculating the amount of included tax.
- Fixed a bug where products of a type that had been switched from having variants to not having variants could end up with an extra Title field on the Edit Product page.
- Fixed an issue where Craft Personal and Client installations where making user groups available to sale and discount conditions.
- Fixed a PHP error that occurred when an order model’s `userId` attribute was set to the ID of a user account that didn’t have a customer record associated with it.
- Fixed a bug where quantity restrictions on a product/variant were not being applied consistently to line items that were added with custom options.
- Fixed some language strings that were not getting static translations applied to them.
- Fixed a bug where Price fields were displaying blank values when they had previously been set to `0`.
- Fixed a bug where `Commerce_TaxCategoriesService::getAllTaxCategories()` could return null values if `getTaxCategoryById()` had been called previously with an invalid tax category ID.

## 0.9.1177 - 2015-11-18

### Changed
- The example templates now display credit card errors more clearly.

### Fixed
- Fixed a bug where products’ and variants’ Stock fields were displaying blank values.

## 0.9.1176 - 2015-11-17

### Added
- Craft Commerce is now translated into German, Dutch, French (FR and CA), and Norwegian.
- Added the “Automatic SKU Format” Product Type setting, which defines what products’/variants’ SKUs should look like when they’re submitted without a value.
- It’s now possible to save arbitrary “options” to line items. When the same purchasable is added to the cart twice, but with different options, it will result in two separate line items rather than one line item with a quantity of 2.
- Order models now have a `totalDiscount` property, which returns the total of all discounts applied to its line items, in addition to the base discount.

### Changed
- The tax engine now records the amount of included tax for each line item, via a new `taxIncluded` property on line item models. (This does not affect existing tax calculation behaviors in any way.)
- Customer data stored in session is now cleared out whenever a user logs in/out, and when a logged-out guest completes their order.
- The example templates have been updated to demonstrate the new Line Item Options feature.
- Address management features are now hidden for guest users in the example templates to avoid confusion.

### Fixed
- Fixed a bug where products/variants that were out of stock would show a blank value for the “Stock” field, rather than “0”.
- Fixed a bug where the `shippingMethod` property returned by Ajax requests to `commerce/cart/*` was getting set to an incorrect value. The property is now set to the shipping method’s handle.

## 0.9.1175 - 2015-11-11

### Added
- Added a new “Show the Title field for variants” setting to Product Types that have variants. When checked, variants of products of that Product Type will get a new “Title” field that can be directly edited by product managers.
- It’s now possible to update an order’s custom fields when posting to the `commerce/cartPayment/pay` controller action.

### Changed
- Renamed `craft.commerce.getShippingMethods()` to `getAvailableShippingMethods()`.
- The shipping method info arrays returned by `craft.commerce.getAvailableShippingMethods()` now include `description` properties, set to the shipping methods’ active rules’ description. It also returns the shipping methods’ `type`.
- The shipping method info arrays returned by `craft.commerce.getAvailableShippingMethods()` are now sorted by their added cost, from cheapest to most expensive.
- Ajax requests to `commerce/cart/*` controller actions now get information about the available shipping methods in the response.
- Customer address info is now purged from the session when a user logs out with an active cart.
- Changes to the payment method in the example templates’ checkout process are now immediately applied to the cart.
- When the Stripe gateway is selected as the Payment Method during checkout we now show an example implementation of token billing with stripe.js

### Fixed
- Fixed a bug where the user-managed shipping methods’ edit URLs were missing a `/` before their IDs.
- Fixed a bug where it was possible to complete an order with a shipping method that was not supposed to be available, per its rules.
- Fixed a bug where it was possible to log out of Craft but still see address data in the cart.
- Fixed a bug where plugin-based shipping methods were getting re-instantiated each time `craft.commerce.getShippingMethods()` was called.
- Fixed a bug where batch product deletion from the Products index page was not also deleting their associated variants.

## 0.9.1173 - 2015-11-09

### Added
- Added a “Business Name” field to customer addresses (accessible via a `businessName` attribute), which replaces the “Company” field (and `company` attribute), and can be used to store customers’ businesses’ names when purchasing on behalf of their company.
- Added a “Business Tax ID” field to customer addresses (accessible via a `businessTaxId` attribute), which can be used to store customers’ businesses’ tax IDs (e.g. VAT) when purchasing on behalf of their company.
- Added a `getCountriesByTaxZoneId()` method to the Tax Zones service.
- Added a `getStatesByTaxZoneId()` method to the Tax Zones service.
- It’s now possible to create new Tax Zones and Tax Categories directly from the Edit Tax Rate page.

### Changed
- The ShippingMethod interface has three new methods: `getType()`, `getId()`, and `getCpEditUrl()`. (`getId()` should always return `null` for third party shipping methods.)
- It is no longer necessary to have created a Tax Zone before accessing Commerce → Settings → Tax Rates and creating a tax rate.
- The “Handle” field on Edit Tax Category pages is now automatically generated based on the “Name” field.
- Plugin-based shipping methods are now listed in Commerce → Settings → Shipping Methods alongside the user-managed ones.
- Orders can now be sorted by ID in the Control Panel.
- Updated the example templates to account for the new `businessName` and `businessTaxId` address attributes.

### Fixed
- Fixed a PHP error that occurred when editing a product if PHP was configured to display strict errors.
- Fixed a bug where products/variants would always show the “Dimensions” and “Weight” fields, even for product types that were configured to hide those fields.
- Fixed a PHP error that occurred when the tax calculator accessed third-party Shipping Methods.
- Fixed a MySQL error that occurred when saving a Tax Rate without a Tax Zone selected.
- Fixed an issue where clicking on the “Settings” global nav item under “Commerce” could direct users to the front-end site.

## 0.9.1171 - 2015-11-05

### Changed
- The “Promotable” and “Free Shipping” field headings on Edit Product pages now act as labels for their respective checkboxes.
- Craft Commerce now logs an error message when an order’s custom status is changed and the notification email’s template cannot be found.
- Commerce Customer Info fields are now read-only. (Customers can still edit their own addresses from the front-end.)
- Craft Commerce now keeps its customers’ emails in sync with their corresponding user accounts’ emails.
- Added a `shortNumber` attribute to order models, making it easy for templates to access the short version of the order number.
- The example templates’ product listings have new and improved icon images.

### Fixed
- Fixed a bug where the “Craft Commerce” link in the global sidebar would direct users to the front-end site, if the `cpTrigger` config setting was not set to `'admin'`.
- Updated the “Post Date” and “Expiry Date” table column headings on the Products index page, which were still labeled “Available On” and “Expires On”.
- Fixed a bug where one of the Market Commerce → Craft Commerce upgrade migrations wouldn’t run on case-sensitive file systems.
- Fixed a PHP error that occurred when viewing an active cart without an address from the Control Panel.
- Fixed a bug where custom field data was not saved via the `commerce/cart/updateCart` controller action if it wasn’t submitted along with other cart updates.
- Added some missing CSRF inputs to the example templates, when CSRF protection is enabled for the site.

### Security
- The example templates’ third party scripts now load over a protocol-relative URL, resolving security warnings.

## 0.9.1170 - 2015-11-04

### Added
- Renamed the plugin from Market Commerce to Craft Commerce.
- Craft Commerce supports One-Click Updating from the Updates page in the Control Panel.
- Gave Craft Commerce a fancy new plugin icon.
- Updated all of the Control Panel templates for improved consistency with Craft 2.5, and improved usability.
- Non-admins can now access Craft Commerce’s Control Panel pages via the “Access Craft Commerce” user permission (with the exception of its Settings section).
- Products are now localizable.
- It’s now possible to create a new sale or discount right from the Products index page, via a new Batch Action.
- It’s now possible to delete products from the Products index page in the Control Panel.
- Product variants are now managed right inline on Edit Product pages, via a new Matrix-inspired UI.
- Added Live Preview and Sharing support to Edit Product pages.
- It’s now possible to create new products right from Product Selector Modals (like the ones used by Products fields).
- Product types now have a “Has dimensions?” setting. The Width, Height, Length, and Weight variant fields will only show up when this is enabled now.
- It’s now possible to update multiple order statuses simultaneously from the Orders index page, via a new Batch Action.
- It’s now possible to delete orders from the Orders index page in the Control Panel.
- The View Order page now uses the same modal window to update order statuses as the Orders index page uses when updating statuses via the Batch Action.
- The View Order page now has “info” icons beside each line item and recorded transaction, for viewing deeper information about them.
- The View Order page now shows adjustments made on the order.
- Renamed the `craft.market` variable to `craft.commerce`.
- Added a new `commerce/cart/updateCart` controller action that can handle customer address/email changes, coupon application, line item additions, and shipping/payment method selections, replacing most of the old Cart actions. (The only other `commerce/cart/*` actions that remain are `updateLineItem`, `removeLineItem`, and `removeAllLineItems`.)
- It’s now possible to use token billing with some gateways, like Stripe, by passing a `token` POST param to the `cartPay/pay` controller action, so your customers’ credit card info never touches your server.
- It’s now possible to access through all custom Order Statuses `craft.commerce.orderStatuses`.
- Added the `itemSubtotalWithSale` attribute to order models, to get the subtotal of all order items before any adjustments have been applied.
- Renamed all class namespaces and prefixes for the Craft Commerce rename.
- Renamed nearly all service method names to be more explicit and follow Craft CMS naming conventions (i.e. `getById()` is now `getOrderById()`).
- All gateways must now implement the GatewayAdapterInterface interface. Craft Commerce provides a BaseGatewayAdapter class that adapts OmniPay gateway classes for this interface.
- Added the `commerce_transactions.onSaveTransaction` event.
- Added the `commerce_addOrderActions` hook.
- Added the `commerce_addProductActions` hook.
- Added the `commerce_defineAdditionalOrderTableAttributes` hook.
- Added the `commerce_defineAdditionalProductTableAttributes` hook.
- Added the `commerce_getOrderTableAttributeHtml` hook.
- Added the `commerce_getProductTableAttributeHtml` hook.
- Added the `commerce_modifyEmail` hook.
- Added the `commerce_modifyOrderSortableAttributes` hook.
- Added the `commerce_modifyOrderSources` hook.
- Added the `commerce_modifyPaymentRequest` hook.
- Added the `commerce_modifyProductSortableAttributes` hook.
- Added the `commerce_modifyProductSources` hook.
- Added the `commerce_registerShippingMethods` hook.

### Changed
- Sales rates and percentages are now entered as a positive number, and can be entered with or without a `%` sign.
- Products are now sorted by Post Date in descending order by default.
- All of the Settings pages have been cleaned up significantly.
- Renamed the `isPaid` order criteria param to `isUnpaid`.
- Renamed products’ `availableOn` and `expiresOn` attributes to `postDate` and `expiryDate`.
- Craft Commerce now records all failed payment transactions and include the gateway response.
- Reduced the number of SQL queries that get executed on order/product listing pages, depending on the attributes being accessed.
- Tax Categories now have “handles” rather than “codes”.
- When a Product Type is changed from having variants to not having variants, all of the existing products’ variants will be deleted, save for the Default Variants.
- If a default zone is not selected on an included tax rate, an error is displayed.
- Improved the extendability of the shipping engine. The new `ShippingMethod` and `ShippingRule` interfaces now allow a plugin to provide their own methods and rules which can dynamically add shipping costs to the cart.
- Added an `$error` argument to `Commerce_CartService::setPaymentMethod()` and `setShippingMethod()`.
- The example templates have been updated for the new variable names and controller actions, and their Twig code has been simplified to be more clear for newcomers (including more detailed explanation comments).
- The example PDF template now includes more information about the order, and a “PAID” stamp graphic.
- The example templates now include a customer address management section.
- Improved the customer address selection UI.

### Removed
- The “Cart Purge Interval” and “Cart Cookie Expiry Settings” have been removed from Control Panel. You will now need to add a `commerce.php` file in craft/config and set those settings from there. (See commerce/config.php for the default values.)
- Removed the default Shipping Method and improved the handling of blank shipping methods.
- Removed customer listing page. Add the Commerce Customer Info field type to your User field layout instead.

### Fixed
- Fixed a bug where you could pass an invalid `purchasableId` to the Cart.
- Fixed a bug where the customer link on the View Order page didn’t go to the user’s profile.
- Fixed a Twig error that occurred if a user manually went to /admin/commerce/orders/new. A 404 error is returned instead now.
- Fixed a bug where it was possible to use currency codes unsupported by OmniPay.
- Fixed a bug where the Mollie gateway was not providing the right token for payment completion.
- Fixed a bug where the `totalShipping` cost was incorrect when items with Free Shipping were in the cart.
- Fixed a bug in the Sale Amount logic.
- Products are now Promotable by default.
- Fixed bug where the logic to determine if an order is paid in full had a rounding error.
