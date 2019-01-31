# Release Notes for Craft Commerce 2.x

## Unreleased

### Added 
- Added the “Tax included in price” tax setting to the Lite edition. ([#654](https://github.com/craftcms/commerce/issues/654))
 
### Changed
- Soft-deleted products are now restorable.

### Fixed
- Fixed an error that occurred when upgrading to Commerce 2 with a database that had missing constraints on the `commerce_orderhistories` table.
- Fixed a bug where sale conditions could be lost when upgrading to Commerce 2. ([#626](https://github.com/craftcms/commerce/issues/626))
- Fixed a PHP error that occurred when saving a product type. ([#645](https://github.com/craftcms/commerce/issues/645))
- Fixed a bug that prevented products from being deleted. ([#650](https://github.com/craftcms/commerce/issues/650))
- Fixed a PHP error that occurred when deleting the cart's line item while running the Lite edition. ([#639](https://github.com/craftcms/commerce/pull/639))
- Fixed a bug where Commerce's general settings weren't saving. ([#655](https://github.com/craftcms/commerce/issues/655))
- Fixed a missing import. ([#643](https://github.com/craftcms/commerce/issues/643))
- Fixed a bug that caused an incorrect tax rate calculation when included taxes had been removed from the price.
- Fixed a SQL error raised when saving a tax rate without a tax zone selected. ([#667](https://github.com/craftcms/commerce/issues/667))
- Fixed an error when refunding a transaction with a localized currency format. ([#659](https://github.com/craftcms/commerce/issues/659))
- Fixed a SQL error caused my missing input validations when saving a Discount. ([#673](https://github.com/craftcms/commerce/issues/673))
- Fixed the credit card form expiry input to allow non-numeric characters. ([#636](https://github.com/craftcms/commerce/issues/#636))

## 2.0.2 - 2019-01-23

### Added
- Added the new Commerce Lite example templates folder `templates/buy`, this is in addition to the existing Commerce Pro example templates folder `templates/shop`. 

### Fixed
- Fixed a PHP error raised when extending the `craft\commerce\base\ShippingMethod` class. ([#634](https://github.com/craftcms/commerce/issues/634))
- Fixed a PHP error that occurred when viewing an order that used a since-deleted shipping method.

## 2.0.1 - 2019-01-17

### Fixed
- Fixed an issue where the “Total Paid”, “Total Price”, and “Total Shipping Cost” Order index page columns were showing incorrect values. ([#632](https://github.com/craftcms/commerce/issues/632))
- Fixed an issue where custom field validation errors did not show up on the Edit Order page. ([#580](https://github.com/craftcms/commerce/issues/580))

### Changed
- Renamed the shipping rule condition from “Mimimum order price” to “Minimum order value” which clarifies the condition is based on item value before discounts and tax.
- Renamed the shipping rule condition from “Maximum order price” to “Maximum order value” which clarifies the condition is based on item value before discounts and tax.

## 2.0.0 - 2019-01-15

### Added
- Craft Commerce has been completely rewritten for Craft 3.
- Emails, gateways, order fields, order statuses, product types, and subscription fields are now stored in the project config.
- Added support for Craft 3.1 project config support.
- Gateways can now provide recurring subscription payments. ([#257](https://github.com/craftcms/commerce/issues/257))
- Added the Store Location setting.
- Customers can now save their credit cards or payment sources stored as tokens in Commerce so customers don’t need to enter their card number on subsequent checkouts. ([#21](https://github.com/craftcms/commerce/issues/21))
- Any custom purchasable can now have sales and discounts applied to them.
- Sales and discouts can now be set on categories of products or purchasables.
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
- Added `cract\commerce\services\ProductTypes::getProductTypesByShippingCategoryId().`
- Added `cract\commerce\services\ProductTypes::getProductTypesByTaxCategoryId().`
- Added `craft\commerce\adjustments\Discount::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED`.
- Added `craft\commerce\base\ShippingMethod`
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
- Added `craft\commerce\services\LineItems::EVENT_BEFORE_POPULATE_LINE_ITEM`
- Added `craft\commerce\services\LineItems::EVENT_BEFORE_SAVE_LINE_ITEM`.
- Added `craft\commerce\services\LineItems::EVENT_CREATE_LINE_ITEM`.
- Added `craft\commerce\services\OrderAdjustments::EVENT_REGISTER_ORDER_ADJUSTERS`.
- Added `craft\commerce\services\OrderHistories::EVENT_ORDER_STATUS_CHANGE`.
- Added `craft\commerce\services\OrderStatuses::archiveOrderStatusById().`
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
- `Customer::$lastUsedBillingAddress`has been replaced with `$primaryBillingAddress`.
- `Customer::$lastUsedShippingAddress`has been replaced with `$primaryShippingAddres`.
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
- Removed `shipping`, `discount`, `tax`, `taxIncluded` attributes from the line item model. Line item's now have line item level adjustments.
- Removed `PurchasableInterface::validateLineItem()`. `getLineItemRules()` should be used instead.
- Removed the `deleteOrderStatusById()` method on the `OrderStatuses` service.
- Removed the `OrderSettings` model, record, and service.
- Removed the `getCountryByAttributes()` method from the `Countries` service.
- Removed the `getStatesByAttributes()` method from the `States` service.
- Removed the `getLastUsedBillingAddress()` and `getLatUsedShippingAddress()` methods from `Customer` models.

### Fixed
- Fixed a bug where a product’s `getCpEditUrl()` method could omit the site handle on multi-site installs. ([craftcms/cms#3089](https://github.com/craftcms/cms/issues/3089))
- Fixed a bug where handles and names for archived gateways were not freed up for re-use. ([#485](https://github.com/craftcms/commerce/issues/485))
