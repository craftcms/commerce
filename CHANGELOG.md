# Release Notes for Craft Commerce

## Unreleased

- Successfully refunding a payment that returns a 'processing' status will no longer show a failed flash message.

## 4.5.2 - 2024-03-06

- Fixed a bug where order status sources weren’t showing count badges on the Orders index page. ([#3397](https://github.com/craftcms/commerce/issues/3397))
- Fixed a bug where discounts weren’t listing more than 128 related purchasables or categories. ([#3379](https://github.com/craftcms/commerce/issues/3379))

## 4.5.1.1 - 2024-03-01

- Fixed a bug where the “Share cart” order index action wasn’t working.
- Fixed a bug where editing an adjustment’s amount could cause the adjustment to lose data on the Edit Order page. ([#3392](https://github.com/craftcms/commerce/issues/3392))

## 4.5.1 - 2024-02-29

- Fixed a SQL error that could occur when updating to Commerce 4 on MySQL. ([#3388](https://github.com/craftcms/commerce/pull/3388))
- Fixed a bug where `craft\commerce\services\Carts::getHasSessionCartNumber()` wasn’t checking the cart cookie. ([#3353](https://github.com/craftcms/commerce/issues/3353))
- Fixed a bug where it wasn’t possible to submit a blank variant title on the Edit Product page. ([#3384](https://github.com/craftcms/commerce/issues/3384))

## 4.5.0 - 2024-02-26

- Removed the Lite edition.
- Deprecated `craft\commerce\models\ShippingMethod::isLite`.
- Deprecated `craft\commerce\models\ShippingRule::isLite`.
- Deprecated `craft\commerce\models\TaxRate::isLite`.
- Deprecated `craft\commerce\models\LiteShippingSettings`.
- Deprecated `craft\commerce\models\LiteTaxSettings`.
- Deprecated `craft\commerce\controllers\LiteShippingController`.
- Deprecated `craft\commerce\controllers\LiteTaxController`.
- Deprecated `craft\commerce\services\ShippingMethods::getLiteShippingMethod()`. `getAllShippingMethods()` should be used instead.
- Deprecated `craft\commerce\services\ShippingMethods::saveLiteShippingMethod()`. `saveShippingMethod()` should be used instead.
- Deprecated `craft\commerce\services\ShippingRules::getLiteShippingRule()`. `getAllShippingRules()` should be used instead.
- Deprecated `craft\commerce\services\ShippingRules::saveLiteShippingRule()`. `saveShippingRule()` should be used instead.
- Deprecated `craft\commerce\services\TaxRates::getLiteTaxRate()`. `getAllTaxRates()` should be used instead.
- Deprecated `craft\commerce\services\TaxRates::saveLiteTaxRate()`. `saveTaxRate()` should be used instead.
- Fixed a SQL error that occurred when running the `commerce/upgrade` command on PostgreSQL. ([#3380](https://github.com/craftcms/commerce/pull/3380))

## 4.4.1.1 - 2024-01-12

- Fixed a PHP error that occurred when saving a sale. ([#3364](https://github.com/craftcms/commerce/issues/3364))

## 4.4.1 - 2024-01-12

- Fixed a SQL error that could occur when updating to Commerce 4.4.0 on MySQL. ([#3367](https://github.com/craftcms/commerce/issues/3367))
- Fixed a PHP error that occurred when saving a discount. ([#3364](https://github.com/craftcms/commerce/issues/3364))

## 4.4.0 - 2024-01-11

- Craft Commerce now requires Craft CMS 4.6.0 or later.
- Added search to the Discounts index. ([#2322](https://github.com/craftcms/commerce/discussions/2322))
- Improved the performance of the Discounts index. ([#3347](https://github.com/craftcms/commerce/issues/3347))
- Improved the performance of the `commerce/upgrade` command. ([#3286](https://github.com/craftcms/commerce/issues/3286))
- Added `craft\commerce\services\Discounts::ensureSortOrder()`.
- Fixed a bug where calling `craft\commerce\services\Carts::forgetCart()` wouldn’t completely clear the cart. ([#3353](https://github.com/craftcms/commerce/issues/3353))
- Fixed a bug where the Edit Order page could become locked when editing an adjustment. ([#3351](https://github.com/craftcms/commerce/issues/3351))
- Fixed a bug that prevented the creation of a non-Stripe subscription. ([#3365](https://github.com/craftcms/commerce/pull/3365))

## 4.3.3 - 2023-12-14

- Improved the performance of variant queries’ `hasProduct` and `hasVariant` params. ([#3325](https://github.com/craftcms/commerce/pull/3325))
- Order statuses with long names no longer wrap on the Orders index page. ([#3335](https://github.com/craftcms/commerce/issues/3335))
- Fixed a bug where carts could get duplicate validation errors. ([3334](https://github.com/craftcms/commerce/issues/3334))
- Fixed a bug where tab selection was inconsistent on Edit Order pages.
- Fixed a bug where sales weren’t respecting elements’ site statuses. ([#3328](https://github.com/craftcms/commerce/issues/3328))
- Fixed a bug where soft-deleted order statuses and line item statuses weren’t getting restored when applying project config changes. ([#3164](https://github.com/craftcms/commerce/issues/3164))
- Fixed a bug where carts weren’t getting restored after signing in.
- Fixed a bug where guests could use discounts with per-user usage limits. ([#3326](https://github.com/craftcms/commerce/issues/3326))
- Fixed a bug where orders with a processing transaction weren’t getting completed.

## 4.3.2 - 2023-10-31

- Product GraphQL queries now support `promotable`, `freeShipping`, `defaultSku`, `defaultHeight`, `defaultLength`, `defaultWidth`, and `defaultWeight` arguments. ([#3307](https://github.com/craftcms/commerce/pull/3307))
- Product GraphQL queries now support `promotable`, `freeShipping`, `defaultSku`, `defaultHeight`, `defaultLength`, `defaultWidth`, `defaultWeight`, and `defaultVariant` fields. ([#3307](https://github.com/craftcms/commerce/pull/3307))
- Fixed a bug where it was possible to select soft-deleted tax categories.
- Fixed a PHP error that occurred when sending an email with a missing PDF filename format. ([#3309](https://github.com/craftcms/commerce/issues/3309))
- Fixed a PHP error that occurred when viewing soft-deleted orders. ([#3308](https://github.com/craftcms/commerce/issues/3308))
- Fixed a bug where saving a shipping zone could fail if a tax zone existed with the same name. ([#3317](https://github.com/craftcms/commerce/issues/3317))
- Fixed a bug where `craft\commerce\services\LineItems::getLineItemById()` wasn’t decoding the snapshot data. ([#3253](https://github.com/craftcms/commerce/issues/3253))

## 4.3.1 - 2023-10-18

- Added the `commerce/gateways/list` command.
- Added the `commerce/gateways/webhook-url` command.
- Improved the performance of the `commerce/upgrade` command. ([#3286](https://github.com/craftcms/commerce/issues/3286))
- Auto-generated variant titles and SKUs are now generated before products are saved. ([#3297](https://github.com/craftcms/commerce/pull/3297))
- Added `craft\commerce\models\ShippingMethodOption::$shippingMethod`. ([#3274](https://github.com/craftcms/commerce/pull/3274), [#3271](https://github.com/craftcms/commerce/issues/3271))
- `craft\commerce\services\Purchasables::EVENT_PURCHASABLE_SHIPPABLE` event handlers can now access the order. ([#3279](https://github.com/craftcms/commerce/pull/3279))
- Fixed a bug where Edit Product pages showed a Delete button for users that didn’t have permission to delete the product. ([#3285](https://github.com/craftcms/commerce/issues/3285))
- Fixed a bug where it was possible to select soft-deleted shipping categories. ([#3272](https://github.com/craftcms/commerce/issues/3272))
- Fixed a bug where the Customer condition rule wasn’t loading correctly. ([#3291](https://github.com/craftcms/commerce/issues/3291))
- Fixed an error that could occur when rendering a PDF. ([#2633](https://github.com/craftcms/commerce/issues/2633))
- Fixed a bug where sales’ and discounts’ timestamps weren’t getting populated. ([#3298](https://github.com/craftcms/commerce/issues/3298))
- Fixed a bug where the `commerce/upgrade` command could create duplicate inactive users. ([#3286](https://github.com/craftcms/commerce/issues/3286))
- Fixed a bug where `commerce/payments/pay` JSON responses were missing the `redirect` key. ([#3265](https://github.com/craftcms/commerce/issues/3265))
- Fixed a bug where gateway URLs could be malformed. ([#3299](https://github.com/craftcms/commerce/issues/3299))

## 4.3.0 - 2023-09-13

- Sales and discounts now support using related entries in their matching item conditions. ([#3134](https://github.com/craftcms/commerce/issues/3134), [#2717](https://github.com/craftcms/commerce/issues/2717))
- It’s now possible to query products by shipping category and tax category. ([#3219](https://github.com/craftcms/commerce/issues/3219))
- Guest customers registering during checkout now have their addresses saved to their account. ([#3203](https://github.com/craftcms/commerce/pull/3203))
- Product conditions can now have “Product Type”, “Variant SKU”, “Variant Has Unlimited Stock”, “Variant Price”, and “Variant Stock” rules. ([#3209](https://github.com/craftcms/commerce/issues/3209))
- Improved the performance of discount recalculation.
- Improved the performance of the `commerce/upgrade` command. ([#3208](https://github.com/craftcms/commerce/pull/3208))
- Added the `commerce/cart/forget-cart` action. ([#3206](https://github.com/craftcms/commerce/issues/3206))
- The `commerce/cart/update-cart` action now accepts `firstName` and `lastName` address parameters. ([#3015](https://github.com/craftcms/commerce/issues/3015))
- Added `craft\commerce\controllers\OrdersController::EVENT_MODIFY_PURCHASABLES_TABLE_QUERY`. ([#3198](https://github.com/craftcms/commerce/pull/3198))
- Added `craft\commerce\elements\Order::$orderCompletedEmail`. ([#3138](https://github.com/craftcms/commerce/issues/3138))
- Added `craft\commerce\elements\db\ProductQuery::$shippingCategoryId`.
- Added `craft\commerce\elements\db\ProductQuery::$taxCategoryId`.
- Added `craft\commerce\elements\db\ProductQuery::shippingCategory()`.
- Added `craft\commerce\elements\db\ProductQuery::shippingCategoryId()`.
- Added `craft\commerce\elements\db\ProductQuery::taxCategory()`.
- Added `craft\commerce\elements\db\ProductQuery::taxCategoryId()`.
- Added `craft\commerce\models\Discount::hasBillingAddressCondition()`.
- Added `craft\commerce\models\Discount::hasCustomerCondition()`.
- Added `craft\commerce\models\Discount::hasOrderCondition()`.
- Added `craft\commerce\models\Discount::hasShippingAddressCondition()`.
- Deprecated payment source creation via the `commerce/subscriptions/subscribe` action.
- Deprecated `craft\commerce\elements\Order::setEmail()`. `Order::setCustomer()` should be used instead.
- Removed the `htmx` option from the`commerce/example-templates` command.
- Removed the `color` option from the`commerce/example-templates` command.
- Added `craft\commerce\events\ModifyPurchasablesTableQueryEvent`. ([#3198](https://github.com/craftcms/commerce/pull/3198))
- Fixed a bug where products/variants could be saved with a minimum quantity that was set higher than the maximum quantity. ([#3234](https://github.com/craftcms/commerce/issues/3234))
- Fixed a bug where `craft\commerce\elements\Order::hasMatchingAddresses()` could incorrectly return `false`. ([#3183](https://github.com/craftcms/commerce/issues/3183))
- Fixed a bug where changing a user’s email could cause additional user elements to be created. ([#3138](https://github.com/craftcms/commerce/issues/3138))
- Fixed a bug where related sales were displaying when creating a new product.
- Fixed a bug where Commerce wasn’t invoking `craft\services\Elements::EVENT_AUTHORIZE_*` event handlers.
- Fixed a bug where discounts’ per user usage counters weren’t getting migrated properly when upgrading to Commerce 4.
- Fixed a bug where address changes weren’t being synced to carts that were using them. ([#3178](https://github.com/craftcms/commerce/issues/3178))
- Fixed a SQL error that could occur when fetching emails. ([#3267](https://github.com/craftcms/commerce/pull/3267))
- Fixed an XSS vulnerability.

## 4.2.11 - 2023-06-05

- Fixed a bug where “Send Email” option text wasn’t getting translated. ([#3172](https://github.com/craftcms/commerce/issues/3172))
- Fixed a bug where discounts’ user condition values weren’t getting migrated properly when upgrading to Commerce 4. ([#3176](https://github.com/craftcms/commerce/issues/3176))

## 4.2.10 - 2023-05-31

- An error notification is now displayed when attempting to delete a user with existing orders or subscriptions. ([#3071](https://github.com/craftcms/commerce/pull/3071), [#3070](https://github.com/craftcms/commerce/pull/3070))
- Added support for linking to products and variants from CKEditor fields. ([#3150](https://github.com/craftcms/commerce/discussions/3150))
- Fixed a bug where custom field conditions weren’t showing when editing a shipping zone.
- Fixed a bug where discounts’ user condition values weren’t getting migrated properly when upgrading to Commerce 4. ([#3176](https://github.com/craftcms/commerce/issues/3176))
- Fixed a bug where users weren’t permitted to update their subscriptions on the front-end. ([#3155](https://github.com/craftcms/commerce/issues/3155))
- Fixed a PHP error that could occur when calling `craft\commerce\services\Payments::processPayment()` without passing the new `$redirectData` argument.

## 4.2.9 - 2023-05-25

- The `commerce/cart/update-cart` action now accepts `clearAddresses`, `clearBillingAddress`, and `clearShippingAddress` params.
- Fixed a JavaScript error that occurred when switching control panel tabs on small screens. ([#3162](https://github.com/craftcms/commerce/issues/3162))
- Fixed a bug where the `commerce/upgrade` command wasn’t migrating discounts’ and coupons’ Max Uses values properly. ([#2947](https://github.com/craftcms/commerce/issues/2947))

## 4.2.8 - 2023-05-03

- Added `craft\commerce\services\Customers::EVENT_UPDATE_PRIMARY_PAYMENT_SOURCE`.
- Fixed a bug where PDFs could be generated using the wrong formatting locale. ([#3145](https://github.com/craftcms/commerce/issues/3145))

## 4.2.7 - 2023-04-13

- Added the “Order Site” order condition rule. ([#3131](https://github.com/craftcms/commerce/issues/3131))
- Email jobs are now reattempted up to five times on failure. ([#3121](https://github.com/craftcms/commerce/pull/3121))
- Fixed a bug where variants weren’t getting propagated properly when new sites were created. ([#3124](https://github.com/craftcms/commerce/issues/3124))
- Fixed a bug where the flash message that was shown for order status changes could be malformed, if there were any errors. ([#3116](https://github.com/craftcms/commerce/issues/3116))
- Fixed a bug where Commerce widgets’ “Order Statuses” settings’ instruction text wasn’t getting translated.
- Fixed a bug where the flash message displayed when tax settings failed to save on Commerce Lite wasn’t getting translated.
- Fixed a bug where the `commerce/upgrade` command could fail if there was a large number of orphaned customers.

## 4.2.6 - 2023-03-22

- Discounts’ “Match Customer” conditions can now have a “Signed In” rule.
- Added `craft\commerce\base\Gateway::showPaymentFormSubmitButton()`
- Added `craft\commmerce\elements\conditions\customer\SignedInConditionRule`.
- The `commerce/payments/pay` action now includes a `redirectData` key in JSON responses.
- Fixed a PHP error that could occur when processing a payment. ([#3092](https://github.com/craftcms/commerce/issues/3092))
- Fixed a bug where cart cookies weren’t getting removed on logout, if the `defaultCookieDomain` Craft config setting was set. ([#3091](https://github.com/craftcms/commerce/pull/3091))
- Fixed a bug where the `validateCartCustomFieldsOnSubmission` setting wasn’t being respected in Craft 4.4. ([#3109](https://github.com/craftcms/commerce/issues/3109))
- Fixed a bug where the “Tax Zone” and “Tax Category” selects could be incorrectly populated when editing a tax category.
- Fixed a PHP error that occurred when saving a tax zone with an empty name on Commerce Lite. ([#3089](https://github.com/craftcms/commerce/issues/3089))
- Fixed a PHP error that occurred when saving shipping settings with empty “Shipping Base Rate” or “Shipping Per Item Rate” settings on Commerce Lite.
- Fixed a bug where the flash message that was shown for order status changes was malformed. ([#3116](https://github.com/craftcms/commerce/issues/3116))
- Fixed a PHP error that could occur when creating an order in the control panel. ([#3115](https://github.com/craftcms/commerce/issues/3115))

## 4.2.5.1 - 2023-02-02

- Fixed a PHP error that occurred when retrieving orders with missing line item descriptions or SKUs. ([#2936](https://github.com/craftcms/commerce/issues/2936))

## 4.2.5 - 2023-02-01

- Added support for searching for orders by customer name. ([#3050](https://github.com/craftcms/commerce/issues/3050))
- Fixed a PHP error that occurred if `null` was passed to `craft\commerce\services\Discounts::getDiscountByCode()`. ([#3045](https://github.com/craftcms/commerce/issues/3045))
- Fixed a bug where a large number of shipping rule category queries could be executed.
- Fixed a PHP error that occurred if a product was re-saved before it had finished propagating to all sites. ([#1954](https://github.com/craftcms/commerce/issues/1954))
- Fixed a PHP error that occurred if `craft\commerce\services\ProductTypes::getEditableProductTypes()` was called when no user was logged in.
- Fixed a PHP error that occurred when saving an invalid shipping method.
- Fixed a bug where gateways’ “Enabled for customers to select during checkout” setting wasn’t properly supporting environment variables. ([#3052](https://github.com/craftcms/commerce/issues/3052))
- Fixed a PHP error that could occur when entering values on an Edit Discount page. ([#3067](https://github.com/craftcms/commerce/issues/3067))
- Fixed a PHP error that could occur when validating an address’s Organization Tax ID field. ([#3046](https://github.com/craftcms/commerce/issues/3046))

## 4.2.4 - 2022-11-29

- The “Customer” order condition rule now supports orders with no customer.

## 4.2.3 - 2022-11-23

- Fixed a bug where saving an invalid tax category failed silently. ([#3013](https://github.com/craftcms/commerce/issues/3013))
- Fixed a bug where using the `autoSetNewCartAddresses` config setting was getting applied for guest carts.
- Fixed an error that could occur when purging inactive carts.
- Fixed a bug where products and variants weren’t always available as link options in Redactor. ([#3041](https://github.com/craftcms/commerce/issues/3041))

## 4.2.2 - 2022-11-06

### Fixed

- Fixed a bug where saving an invalid tax category doesn't return an error notice.
- Fixed an error that could occur when purging inactive carts.
- Fixed a bug where the `commerce/cart/update-cart` action wasn’t fully clearing the cart when the `clearLineItems` param was submitted, if the quantity of an exsiting line item was being increased in the same request. ([#3014](https://github.com/craftcms/commerce/issues/3014))
- Fixed an error that could occur when purging a large number of inactive carts.
- Fixed an error where addresses were assumed to have an owner. ([#3021](https://github.com/craftcms/commerce/pull/3021))

## 4.2.1 - 2022-10-27

- Fixed an error that occurred when viewing tax categories.
- Fixed a bug where the Top Products widget wasn’t showing the correct revenue total.
- Added `craft\commerce\models\TaxCategory::dateDeleted`.
- Added `craft\commerce\models\ShippingCategory::dateDeleted`.

## 4.2.0 - 2022-10-26

### Store Management
- Discounts’ “Match Customer” conditions can now have a “Has Orders” rule.
- Order conditions can now have a “Completed” rule.
- Order conditions can now have a “Customer” rule.
- Order conditions can now have a “Date Ordered” rule.
- Order conditions can now have a “Has Purchasable” rule.
- Order conditions can now have a “Item Subtotal” rule.
- Order conditions can now have a “Order Status” rule.
- Order conditions can now have a “Paid” rule.
- Order conditions can now have a “Reference” rule.
- Order conditions can now have a “Shipping Method” rule.
- Order conditions can now have a “Total” rule.
- Order conditions can now have a “Total Discount” rule.
- Order conditions can now have a “Total Price” rule.
- Order conditions can now have a “Total Qty” rule.
- Order conditions can now have a “Total Tax” rule.
- It’s now possible to assign primary payment sources on customers.
- It’s now possible to set the quantity when adding a line item on the Edit Order page. ([#2993](https://github.com/craftcms/commerce/discussions/2993))
- The “Update Order Status…” bulk order action now returns a more helpful response message.

### Administration
- Added the `autoSetPaymentSource` config setting, which can be enabled to automatically set a customers’ primary payment sources on new carts.
- Shipping and tax categories are now archived instead of deleted.

### Development
- Order queries now have `itemTotal`, `itemSubtotal`, `shippingMethodHandle`, `totalDiscount`, `total`, `totalPaid`, `totalPrice`, `totalQty`, and `totalTax` params.
- Order queries’ `reference` params now accept a wider range of values.
- `commerce/cart/*` actions now return `shippingAddress` and `billingAddress` values in JSON responses. ([#2921](https://github.com/craftcms/commerce/issues/2921))

### Extensibility
- Added `craft\commerce\base\Stat::getOrderStatuses()`.
- Added `craft\commerce\base\Stat::setOrderStatuses()`.
- Added `craft\commerce\base\StatInterface::getOrderStatuses()`.
- Added `craft\commerce\base\StatInterface::setOrderStatuses()`.
- Added `craft\commerce\base\StatWidgetTrait`.
- Added `craft\commerce\behaviors\CustomerBehavoir::getPrimaryPaymentSource()`.
- Added `craft\commerce\behaviors\CustomerBehavoir::getPrimaryPaymentSourceId()`.
- Added `craft\commerce\behaviors\CustomerBehavoir::setPrimaryPaymentSourceId()`.
- Added `craft\commerce\controllers\PaymentSourcesController::actionSetPrimaryPaymentSource()`.
- Added `craft\commerce\elements\Order::$storedTotalQty`.
- Added `craft\commerce\elements\Order::autoSetPaymentSource()`.
- Added `craft\commerce\elements\conditions\customers\HasOrdersConditionRule`.
- Added `craft\commerce\elements\conditions\orders\CompletedConditionRule`.
- Added `craft\commerce\elements\conditions\orders\CustomerConditionRule`.
- Added `craft\commerce\elements\conditions\orders\DateOrderedConditionRule`.
- Added `craft\commerce\elements\conditions\orders\HasPurchasableConditionRule`.
- Added `craft\commerce\elements\conditions\orders\ItemSubtotalConditionRule`.
- Added `craft\commerce\elements\conditions\orders\ItemTotalConditionRule`.
- Added `craft\commerce\elements\conditions\orders\OrderCurrencyValuesAttributeConditionRule`.
- Added `craft\commerce\elements\conditions\orders\OrderStatusConditionRule`.
- Added `craft\commerce\elements\conditions\orders\OrderTextValuesAttributeConditionRule`.
- Added `craft\commerce\elements\conditions\orders\PaidConditionRule`.
- Added `craft\commerce\elements\conditions\orders\ReferenceConditionRule`.
- Added `craft\commerce\elements\conditions\orders\ShippingMethodConditionRule`.
- Added `craft\commerce\elements\conditions\orders\TotalConditionRule`.
- Added `craft\commerce\elements\conditions\orders\TotalDiscountConditionRule`.
- Added `craft\commerce\elements\conditions\orders\TotalPriceConditionRule`.
- Added `craft\commerce\elements\conditions\orders\TotalQtyConditionRule`.
- Added `craft\commerce\elements\conditions\orders\TotalTaxConditionRule`.
- Added `craft\commerce\elements\db\OrderQuery::$itemSubtotal`.
- Added `craft\commerce\elements\db\OrderQuery::$itemTotal`.
- Added `craft\commerce\elements\db\OrderQuery::$shippingMethodHandle`.
- Added `craft\commerce\elements\db\OrderQuery::$totalDiscount`.
- Added `craft\commerce\elements\db\OrderQuery::$totalPaid`.
- Added `craft\commerce\elements\db\OrderQuery::$totalPrice`.
- Added `craft\commerce\elements\db\OrderQuery::$totalQty`.
- Added `craft\commerce\elements\db\OrderQuery::$totalTax`.
- Added `craft\commerce\elements\db\OrderQuery::$total`.
- Added `craft\commerce\elements\db\OrderQuery::itemSubtotal()`.
- Added `craft\commerce\elements\db\OrderQuery::itemTotal()`.
- Added `craft\commerce\elements\db\OrderQuery::shippingMethodHandle()`.
- Added `craft\commerce\elements\db\OrderQuery::total()`.
- Added `craft\commerce\elements\db\OrderQuery::totalDiscount()`.
- Added `craft\commerce\elements\db\OrderQuery::totalPaid()`.
- Added `craft\commerce\elements\db\OrderQuery::totalPrice()`.
- Added `craft\commerce\elements\db\OrderQuery::totalQty()`.
- Added `craft\commerce\elements\db\OrderQuery::totalTax()`.
- Added `craft\commerce\models\PaymentSource::getIsPrimary()`.
- Added `craft\commerce\models\Settings::$autoSetPaymentSource`.
- Added `craft\commerce\records\Customer::$primaryPaymentSourceId`.
- Added `craft\commerce\services\savePrimaryPaymentSourceId()`.
- `craft\commerce\elements\Order::hasMatchingAddresses()` now has an `$attributes` argument, which can be used to customize which address attributes should be checked.
- Deprecated `craft\commerce\elements\Order::getShippingMethod()`. `$shippingMethodName` and `$shippingMethodHandle` should be used instead.

### System
- Craft Commerce now requires Craft CMS 4.3.0 or later.
- Fixed a bug where it wasn't possible to use a path value for the `loadCartRedirectUrl` setting. ([#2992](https://github.com/craftcms/commerce/pull/2992))
- Fixed a bug where custom shipping methods weren’t applying to orders properly. ([#2986](https://github.com/craftcms/commerce/issues/2986))
- Fixed a bug where passing an invalid product type handle into product queries’ `type` params wouldn’t have any effect. ([#2966](https://github.com/craftcms/commerce/issues/2966))
- Fixed a bug where payments made from Edit Order pages weren’t factoring in gateways’ `availableForUseWithOrder()` methods. ([#2988](https://github.com/craftcms/commerce/issues/2988))
- Fixed a bug where the Emails index page wasn’t showing emails’ template paths. ([#3000](https://github.com/craftcms/commerce/issues/3000))
- Fixed a bug where product slideout editors were showing additional status fields. ([#3010](https://github.com/craftcms/commerce/issues/3010))

## 4.1.3 - 2022-10-07

### Changed
- The `commerce/downloads/pdf` action now accepts an `inline` param. ([#2981](https://github.com/craftcms/commerce/pull/2981))

### Fixed
- Fixed a SQL error that occurred when restoring a soft-deleted product. ([#2982](https://github.com/craftcms/commerce/issues/2982))
- Fixed a bug where the Edit Product page wasn’t handling site selection changes properly. ([#2971](https://github.com/craftcms/commerce/issues/2971))
- Fixed a bug where it wasn't possible to add variants to a sale from the Edit Product page. ([#2976](https://github.com/craftcms/commerce/issues/2976))
- Fixed a bug where primary addresses weren’t being automatically set on the Edit Order page. ([#2963](https://github.com/craftcms/commerce/issues/2963))
- Fixed a bug where it wasn’t possible to change the default order status. ([#2915](https://github.com/craftcms/commerce/issues/2915))

## 4.1.2 - 2022-09-15

### Fixed
- Fixed a SQL error that could occur when updating to Commerce 4 on MySQL.
- Fixed an error that could when sorting orders by address attributes. ([#2956](https://github.com/craftcms/commerce/issues/2956))
- Fixed a bug where it wasn’t possible to save decimal numbers for variant dimensions. ([#2540](https://github.com/craftcms/commerce/issues/2540))
- Fixed a bug where the Edit Product page wasn’t handling site selection changes properly. ([#2920](https://github.com/craftcms/commerce/issues/2920))
- Fixed a bug where partial elements were not being deleted during garbage collection.
- Fixed a bug where orders’ item subtotals weren’t being saved to the database.
- Fixed a bug where the “Per Item Amount Off” setting on Edit Discount pages was stripping decimal values for locales that use commas for decimal symbols. ([#2937](https://github.com/craftcms/commerce/issues/2937))

## 4.1.1 - 2022-09-01

### Fixed
- Fixed a bug where Edit Subscription pages were blank. ([#2913](https://github.com/craftcms/commerce/issues/2913))
- Fixed a bug where `craft\commerce\elements\Order::hasMatchingAddresses()` wasn’t checking the `fullName` property. ([#2917](https://github.com/craftcms/commerce/issues/2917))
- Fixed a bug where discounts’ Purchase Total values weren’t getting saved.
- Fixed a bug where discounts’ shipping address conditions were being saved as billing address conditions. ([#2938](https://github.com/craftcms/commerce/issues/2938))
- Fixed an error that occurred when exporting orders using the “Expanded” export type. ([#2953](https://github.com/craftcms/commerce/issues/2953))
- Fixed a bug where it wasn’t possible to clear out variants’ min and max quantities. ([#2954](https://github.com/craftcms/commerce/issues/2954))

## 4.1.0 - 2022-07-19

### Added
- Tax rates now have a “Unit price” taxable subject option. ([#2883](https://github.com/craftcms/commerce/pull/2883))
- The Total Revenue widget can now show the total paid, rather than the total invoiced. ([#2852](https://github.com/craftcms/commerce/issues/2852))
- Added the `commerce/transfer-customer-data` command.
- Added `craft\commerce\elements\Order::EVENT_BEFORE_APPLY_ADD_NOTICE`. ([#2676](https://github.com/craftcms/commerce/issues/2676))
- Added `craft\commerce\elements\Order::hasMatchingAddresses()`.
- Added `craft\commerce\services\Customers::transferCustomerData()`. ([#2801](https://github.com/craftcms/commerce/pull/2801))
- Added `craft\commerce\stats\TotalRevenue::$type`.
- Added `craft\commerce\stats\TotalRevenue::TYPE_TOTAL_PAID`.
- Added `craft\commerce\stats\TotalRevenue::TYPE_TOTAL`.
- Added `craft\commerce\widgets\TotalRevenue::$type`.

### Changed
- Craft Commerce now requires Dompdf 2.0.0 or later. ([#2879](https://github.com/craftcms/commerce/pull/2879))
- Addresses submitted to the cart are now validated. ([#2874](https://github.com/craftcms/commerce/pull/2874))
- Garbage collection now removes any orphaned variants, as well as partial donation, order, product, subscription, and variant data.
- `craft\commerce\elements\Product` now supports the `EVENT_DEFINE_CACHE_TAGS` event.
- `craft\commerce\elements\Variant` now supports the `EVENT_DEFINE_CACHE_TAGS` event.

### Fixed
- Fixed an error that occurred when disabling all variants on Edit Product pages.
- Fixed a bug where order address titles weren’t being updated correctly.
- Fixed a bug where it was possible to save an order with the same billing and shipping address IDs. ([#2841](https://github.com/craftcms/commerce/issues/2841))
- Fixed a bug where order addresses were not being saved with the `live` scenario.
- Fixed a PHP error that occurred when editing a subscription with custom fields.
- Fixed an infinite recursion bug that occurred when `autoSetCartShippingMethodOption` was enabled. ([#2875](https://github.com/craftcms/commerce/issues/2875))
- Fixed a bug where product slideout editors were attempting to create provisional drafts. ([#2886](https://github.com/craftcms/commerce/issues/2886))

## 4.0.4 - 2022-06-22

> {note} If you’ve already upgraded a site to Commerce 4, please go to **Commerce** → **Promotions** → **Discounts** and review your discounts’ coupons’ Max Uses values, as the `commerce/upgrade` command wasn’t migrating those values properly before this release.

### Fixed
- Fixed a bug where `craft\commerce\services\PaymentSources::getAllGatewayPaymentSourcesByUserId()` wasn’t passing along the user ID to `getAllPaymentSourcesByCustomerId()`.
- Fixed an error that could occur when using a discount with a coupon code.
- Fixed a bug where it wasn’t possible to delete a shipping rule. ([#2857](https://github.com/craftcms/commerce/issues/2857))
- Fixed a bug where it wasn’t possible to subscribe and create a payment source simultaneously. ([#2834](https://github.com/craftcms/commerce/pull/2834))
- Fixed inaccurate PHP type declarations.
- Fixed errors that could occur when expiring, cancelling, or suspending a subscription. ([#2831](https://github.com/craftcms/commerce/issues/2831))
- Fixed a bug where the Order Value condition rule wasn’t working.
- Fixed a bug where the `commerce/upgrade` command wasn’t migrating discounts’ coupons’ Max Uses values properly.

## 4.0.3 - 2022-06-09

### Deprecated
- Deprecated `craft\commerce\services\Orders::pruneDeletedField()`.
- Deprecated `craft\commerce\services\ProductType::pruneDeletedField()`.
- Deprecated `craft\commerce\services\Subscriptions::pruneDeletedField()`.

### Fixed
- Fixed a PHP error that could occur when saving a shipping rule. ([#2824](https://github.com/craftcms/commerce/issues/2824))
- Fixed a PHP error that could occur when saving a sale. ([#2827](https://github.com/craftcms/commerce/issues/2827))
- Fixed a bug where `administrativeArea` data wasn’t being saved for an address in the example templates. ([#2840](https://github.com/craftcms/commerce/issues/2840))

## 4.0.2 - 2022-06-03

### Fixed
- Fixed a bug where it wasn’t possible to set a coupon’s Max Uses setting to `0`.
- Fixed UI bugs in the “Update Order Status” modal. ([#2821](https://github.com/craftcms/commerce/issues/2821))
- Fixed a bug where the `commerce/upgrade` console command caused customer discount uses to be reset.
- Fixed a bug where the `commerce/upgrade` console command would fail when multiple orders used the same email address with different casing.

## 4.0.1 - 2022-05-18

### Changed
- Address forms in the example templates now include any Plain Text custom fields in the address field layout.

### Fixed
- Fixed a bug where the `autoSetNewCartAddresses` setting didn’t have any effect. ([#2804](https://github.com/craftcms/commerce/issues/2804))
- Fixed a PHP error that occurred when making a payment on the Edit Order page. ([#2795](https://github.com/craftcms/commerce/issues/2795))
- Fixed a PHP error that occurred when duplicating addresses that wasn’t owned by a user.
- Fixed a bug where address cards appeared to be editable when viewing completed orders. ([#2817](https://github.com/craftcms/commerce/issues/2817))
- Fixed a front-end validation error that was raised incorrectly on address inputs in the example templates. ([#2777](https://github.com/craftcms/commerce/pull/2777))

## 4.0.0 - 2022-05-04

### Added
- Customers are now native Craft user elements. ([#2524](https://github.com/craftcms/commerce/discussions/2524), [2385](https://github.com/craftcms/commerce/discussions/2385))
- Discounts can now have condition builders, enabling flexible matching based on the order, user, and addresses. ([#2290](https://github.com/craftcms/commerce/discussions/2290),  [#2296](https://github.com/craftcms/commerce/discussions/2296), [#2299](https://github.com/craftcms/commerce/discussions/2299))
- Shipping zones can now have condition builders, enabling flexible matching based on the address. ([#2290](https://github.com/craftcms/commerce/discussions/2290), [#2296](https://github.com/craftcms/commerce/discussions/2296))
- Tax zones can now have condition builders, enabling flexible matching based on the address. ([#2290](https://github.com/craftcms/commerce/discussions/2290), [#2296](https://github.com/craftcms/commerce/discussions/2296))
- Discounts can now have multiple coupon codes, each with their own usage rules. ([#2377](https://github.com/craftcms/commerce/discussions/2377), [#2303](https://github.com/craftcms/commerce/discussions/2303), [#2713](https://github.com/craftcms/commerce/pull/2713))
- It’s now possible to bulk-generate coupon codes.
- It’s now possible to create orders from the Edit User page.
- Added a “Commerce” panel to the Debug Toolbar.
- Added “Edit”, “Create”, and “Delete” permissions for product types, sales, and discounts. ([#174](https://github.com/craftcms/commerce/issues/174), [#2400](https://github.com/craftcms/commerce/discussions/2400))
- Added the `|commercePaymentFormNamespace` Twig filter.
- Added `craft\commerce\base\Zone`.
- Added `craft\commerce\behaviors\CustomerAddressBehavior`.
- Added `craft\commerce\behaviors\CustomerBehavior`.
- Added `craft\commerce\console\controllers\UpgradeController`.
- Added `craft\commerce\controllers\DiscountsController::DISCOUNT_COUNTER_TYPE_EMAIL`.
- Added `craft\commerce\controllers\DiscountsController::DISCOUNT_COUNTER_TYPE_TOTAL`.
- Added `craft\commerce\controllers\DiscountsController::DISCOUNT_COUNTER_TYPE_USER`.
- Added `craft\commerce\controllers\DiscountsController::actionGenerateCoupons()`.
- Added `craft\commerce\controllers\OrdersController::actionCreateCustomer()`.
- Added `craft\commerce\controllers\OrdersController::actionGetCustomerAddresses()`.
- Added `craft\commerce\controllers\OrdersController::actionGetOrderAddress()`.
- Added `craft\commerce\controllers\OrdersController::actionValidateAddress()`.
- Added `craft\commerce\controllers\OrdersController::enforceManageOrderPermissions()`.
- Added `craft\commerce\controllers\SubscriptionsController::enforceManageSubscriptionPermissions()`.
- Added `craft\commerce\elements\Order::$sourceBillingAddressId`
- Added `craft\commerce\elements\Order::$sourceShippingAddressId`
- Added `craft\commerce\elements\Product::canCreateDrafts()`.
- Added `craft\commerce\elements\Product::canDelete()`.
- Added `craft\commerce\elements\Product::canDeleteForSite()`.
- Added `craft\commerce\elements\Product::canDuplicate()`.
- Added `craft\commerce\elements\Product::canSave()`.
- Added `craft\commerce\elements\Product::canView()`.
- Added `craft\commerce\elements\Subscription::canView()`.
- Added `craft\commerce\elements\actions\UpdateOrderStatus::$suppressEmails`.
- Added `craft\commerce\events\CommerceDebugPanelDataEvent`.
- Added `craft\commerce\events\OrderStatusEmailsEvent`.
- Added `craft\commerce\events\PdfRenderEvent`.
- Added `craft\commerce\fieldlayoutelements\UserAddressSettings`.
- Added `craft\commerce\helpers\DebugPanel`.
- Added `craft\commerce\helpers\PaymentForm`.
- Added `craft\commerce\models\Coupon`.
- Added `craft\commerce\models\Discount::$couponFormat`.
- Added `craft\commerce\models\Discount::getCoupons()`.
- Added `craft\commerce\models\Discount::setCoupons()`.
- Added `craft\commerce\models\OrderHistory::$userId`.
- Added `craft\commerce\models\OrderHistory::$userName`.
- Added `craft\commerce\models\OrderHistory::getUser()`.
- Added `craft\commerce\models\ShippingAddressZone::condition`.
- Added `craft\commerce\models\Store`.
- Added `craft\commerce\models\TaxAddressZone::condition`.
- Added `craft\commerce\plugin\Services::getCoupons()`.
- Added `craft\commerce\record\OrderHistory::$userName`.
- Added `craft\commerce\records\Coupon`.
- Added `craft\commerce\records\OrderHistory::$userId`.
- Added `craft\commerce\records\OrderHistory::getUser()`.
- Added `craft\commerce\service\Store`.
- Added `craft\commerce\services\Carts::$cartCookieDuration`.
- Added `craft\commerce\services\Carts::$cartCookie`.
- Added `craft\commerce\services\Coupons`.
- Added `craft\commerce\services\Customers::ensureCustomer()`.
- Added `craft\commerce\services\Customers::savePrimaryBillingAddressId()`.
- Added `craft\commerce\services\Customers::savePrimaryShippingAddressId()`.
- Added `craft\commerce\services\Discounts::clearUserUsageHistoryById()`.
- Added `craft\commerce\services\OrderStatuses::EVENT_ORDER_STATUS_CHANGE_EMAILS`.
- Added `craft\commerce\services\Pdfs::EVENT_BEFORE_DELETE_PDF`.
- Added `craft\commerce\services\ProductTypes::getCreatableProductTypeIds()`.
- Added `craft\commerce\services\ProductTypes::getCreatableProductTypes()`.
- Added `craft\commerce\services\ProductTypes::getEditableProductTypeIds()`.
- Added `craft\commerce\services\ProductTypes::hasPermission()`.
- Added `craft\commerce\validators\CouponValidator`.
- Added `craft\commerce\validators\StoreCountryValidator`.
- Added `craft\commerce\web\assets\coupons\CouponsAsset`.

### Changed
- Craft Commerce now requires Craft CMS 4.0.0-RC2 or later.
- Tax rate inputs no longer require the percent symbol.
- Subscription plans are no longer accessible via old Control Panel URLs.
- Addresses can no longer be related to both a user’s address book and an order at the same time. ([#2457](https://github.com/craftcms/commerce/discussions/2457))
- Gateways’ `isFrontendEnabled` settings now support environment variables.
- The active cart number is now stored in a cookie rather than the PHP session data, so it can be retained across browser reboots. ([#2790](https://github.com/craftcms/commerce/pull/2790))
- The installer now archives any database tables that were left behind by a previous Craft Commerce installation.
- `commerce/*` actions no longer accept `orderNumber` params. `number` can be used instead.
- `commerce/cart/*` actions no longer accept `cartUpdatedNotice` params. `successMessage` can be used instead.
- `commerce/cart/*` actions no longer include `availableShippingMethods` in their JSON responses. `availableShippingMethodOptions` can be used instead.
- `commerce/payment-sources/*` actions no longer include `paymentForm` in their JSON responses. `paymentFormErrors` can be used instead.
- `commerce/payments/*` actions now expect payment form fields to be namespaced with the `|commercePaymentFormNamespace` Twig filter’s response.
- `craft\commerce\elements\Order::getCustomer()` now returns a `craft\elements\User` object.
- `craft\commerce\elements\Product::getVariants()`, `getDefaultVariant()`, `getCheapestVariant()`, `getTotalStock()`, and `getHasUnlimitedStock()` now only return data related to enabled variants by default.
- `craft\commerce\model\ProductType::$titleFormat` was renamed to `$variantTitleFormat`.
- `craft\commerce\models\TaxRate::getRateAsPercent()` now returns a localized value.
- `craft\commerce\services\LineItems::createLineItem()` no longer has an `$orderId` argument.
- `craft\commerce\services\LineItems::resolveLineItem()` now has an `$order` argument rather than `$orderId`.
- `craft\commerce\services\Pdfs::EVENT_AFTER_RENDER_PDF` now raises `craft\commerce\events\PdfRenderEvent` rather than `PdfEvent`.
- `craft\commerce\services\Pdfs::EVENT_AFTER_SAVE_PDF` now raises `craft\commerce\events\PdfEvent` rather than `PdfSaveEvent`.
- `craft\commerce\services\Pdfs::EVENT_BEFORE_RENDER_PDF` now raises `craft\commerce\events\PdfRenderEvent` rather than `PdfEvent`.
- `craft\commerce\services\Pdfs::EVENT_BEFORE_SAVE_PDF` now raises `craft\commerce\events\PdfEvent` rather than `PdfSaveEvent`.
- `craft\commerce\services\ShippingMethods::getAvailableShippingMethods()` has been renamed to `getMatchingShippingMethods()`.
- `craft\commerce\services\Variants::getAllVariantsByProductId()` now accepts a `$includeDisabled` argument.

### Deprecated
- Deprecated `craft\commerce\elements\Order::getUser()`. `getCustomer()` should be used instead.
- Deprecated `craft\commerce\services\Carts::getCartName()`. `$cartCookie['name']` should be used instead.
- Deprecated `craft\commerce\services\Plans::getAllGatewayPlans()`. `getPlansByGatewayId()` should be used instead.
- Deprecated `craft\commerce\services\Subscriptions::doesUserHaveAnySubscriptions()`. `doesUserHaveSubscriptions()` should be used instead.
- Deprecated `craft\commerce\services\Subscriptions::getSubscriptionCountForPlanById()`. `getSubscriptionCountByPlanId()` should be used instead.
- Deprecated `craft\commerce\services\TaxRates::getTaxRatesForZone()`. `getTaxRatesByTaxZoneId()` should be used instead.
- Deprecated `craft\commerce\services\Transactions::deleteTransaction()`. `deleteTransactionById()` should be used instead.

### Removed
- Removed the `orderPdfFilenameFormat` setting.
- Removed the `orderPdfPath` setting.
- Removed the `commerce-manageCustomers` permission.
- Removed the `commerce-manageProducts` permission.
- Removed `json_encode_filtered` Twig filter.
- Removed the `commerce/orders/purchasable-search` action. `commerce/orders/purchasables-table` can be used instead.
- Removed `Plugin::getInstance()->getPdf()`. `getPdfs()` can be used instead.
- Removed `craft\commerce\Plugin::t()`. `Craft::t('commerce', 'My String')` can be used instead.
- Removed `craft\commerce\base\AddressZoneInterface`. `craft\commerce\base\ZoneInterface` can be used instead.
- Removed `craft\commerce\base\OrderDeprecatedTrait`.
- Removed `craft\commerce\controllers\AddressesController`.
- Removed `craft\commerce\controllers\CountriesController`.
- Removed `craft\commerce\controllers\CustomerAddressesController`.
- Removed `craft\commerce\controllers\CustomersController`.
- Removed `craft\commerce\controllers\PlansController::actionRedirect()`.
- Removed `craft\commerce\controllers\ProductsPreviewController::actionSaveProduct()`.
- Removed `craft\commerce\controllers\ProductsPreviewController::enforceProductPermissions()`.
- Removed `craft\commerce\controllers\StatesController`.
- Removed `craft\commerce\elements\Order::getAdjustmentsTotalByType()`. `getTotalTax()`, `getTotalDiscount()`, or `getTotalShippingCost()` can be used instead.
- Removed `craft\commerce\elements\Order::getAvailableShippingMethods()`. `getAvailableShippingMethodOptions()` can be used instead.
- Removed `craft\commerce\elements\Order::getOrderLocale()`. `$orderLanguage` can be used instead.
- Removed `craft\commerce\elements\Order::getShippingMethodId()`. `getShippingMethodHandle()` can be used instead.
- Removed `craft\commerce\elements\Order::getShouldRecalculateAdjustments()`. `getRecalculationMode()` can be used instead.
- Removed `craft\commerce\elements\Order::getTotalTaxablePrice()`. The taxable price is now calculated within the tax adjuster.
- Removed `craft\commerce\elements\Order::removeEstimatedBillingAddress()`. `setEstimatedBillingAddress(null)` can be used instead.
- Removed `craft\commerce\elements\Order::removeEstimatedShippingAddress()`. `setEstimatedShippingAddress(null)` can be used instead.
- Removed `craft\commerce\elements\Order::setShouldRecalculateAdjustments()`. `setRecalculationMode()` can be used instead.
- Removed `craft\commerce\elements\actions\DeleteOrder`. `craft\elements\actions\Delete` can be used instead.
- Removed `craft\commerce\elements\actions\DeleteProduct`. `craft\elements\actions\Delete` can be used instead.
- Removed `craft\commerce\elements\traits\OrderDeprecatedTrait`.
- Removed `craft\commerce\events\AddressEvent`.
- Removed `craft\commerce\events\CustomerAddressEvent`.
- Removed `craft\commerce\events\CustomerEvent`.
- Removed `craft\commerce\events\DefineAddressLinesEvent`. `craft\services\Addresses::formatAddress()` can be used instead.
- Removed `craft\commerce\events\LineItemEvent::isValid`.
- Removed `craft\commerce\events\PdfSaveEvent`.
- Removed `craft\commerce\helpers\Localization::formatAsPercentage()`.
- Removed `craft\commerce\models\Country`.
- Removed `craft\commerce\models\Discount::$code`.
- Removed `craft\commerce\models\Discount::getDiscountUserGroups()`.
- Removed `craft\commerce\models\Discount::getUserGroupIds()`. Discount user groups were migrated to the customer condition rule.
- Removed `craft\commerce\models\Discount::setUserGroupIds()`. Discount user groups were migrated to the customer condition rule.
- Removed `craft\commerce\models\Email::getPdfTemplatePath()`. `getPdf()->getTemplatePath()` can be used instead.
- Removed `craft\commerce\models\LineItem::getAdjustmentsTotalByType()`. `getTax()`, `getDiscount()`, or `getShippingCost()` can be used instead.
- Removed `craft\commerce\models\LineItem::setSaleAmount()`.
- Removed `craft\commerce\models\OrderHistory::$customerId`. `$userId` can be used instead.
- Removed `craft\commerce\models\OrderHistory::getCustomer()`. `getUser()` can be used instead.
- Removed `craft\commerce\models\ProductType::getLineItemFormat()`.
- Removed `craft\commerce\models\ProductType::setLineItemFormat()`.
- Removed `craft\commerce\models\Settings::$showCustomerInfoTab`. `$showEditUserCommerceTab` can be used instead.
- Removed `craft\commerce\models\ShippingAddressZone::getCountries()`.
- Removed `craft\commerce\models\ShippingAddressZone::getCountriesNames()`.
- Removed `craft\commerce\models\ShippingAddressZone::getCountryIds()`.
- Removed `craft\commerce\models\ShippingAddressZone::getStateIds()`.
- Removed `craft\commerce\models\ShippingAddressZone::getStates()`.
- Removed `craft\commerce\models\ShippingAddressZone::getStatesNames()`.
- Removed `craft\commerce\models\ShippingAddressZone::isCountryBased`.
- Removed `craft\commerce\models\State`.
- Removed `craft\commerce\models\TaxAddressZone::getCountries()`.
- Removed `craft\commerce\models\TaxAddressZone::getCountriesNames()`.
- Removed `craft\commerce\models\TaxAddressZone::getCountryIds()`.
- Removed `craft\commerce\models\TaxAddressZone::getStateIds()`.
- Removed `craft\commerce\models\TaxAddressZone::getStates()`.
- Removed `craft\commerce\models\TaxAddressZone::getStatesNames()`.
- Removed `craft\commerce\models\TaxAddressZone::isCountryBased`.
- Removed `craft\commerce\queue\jobs\ConsolidateGuestOrders`.
- Removed `craft\commerce\records\Country`.
- Removed `craft\commerce\records\CustomerAddress`. `craft\records\Address` can be used instead.
- Removed `craft\commerce\records\Discount::CONDITION_USER_GROUPS_ANY_OR_NONE`. Discount user groups were migrated to the customer condition rule.
- Removed `craft\commerce\records\Discount::CONDITION_USER_GROUPS_EXCLUDE`. Discount user groups were migrated to the customer condition rule.
- Removed `craft\commerce\records\Discount::CONDITION_USER_GROUPS_INCLUDE_ALL`. Discount user groups were migrated to the customer condition rule.
- Removed `craft\commerce\records\Discount::CONDITION_USER_GROUPS_INCLUDE_ANY`. Discount user groups were migrated to the customer condition rule.
- Removed `craft\commerce\records\DiscountUserGroup`.
- Removed `craft\commerce\records\OrderHistory::getCustomer()`. `getUser()` can be used instead.
- Removed `craft\commerce\records\ShippingZoneCountry`.
- Removed `craft\commerce\records\ShippingZoneState`.
- Removed `craft\commerce\records\State`.
- Removed `craft\commerce\records\TaxZoneCountry`.
- Removed `craft\commerce\records\TaxZoneState`.
- Removed `craft\commerce\services\Addresses::purgeOrphanedAddresses()`.
- Removed `craft\commerce\services\Addresses`.
- Removed `craft\commerce\services\Countries`.
- Removed `craft\commerce\services\Customers::EVENT_AFTER_SAVE_CUSTOMER_ADDRESS`.
- Removed `craft\commerce\services\Customers::EVENT_AFTER_SAVE_CUSTOMER`.
- Removed `craft\commerce\services\Customers::EVENT_BEFORE_SAVE_CUSTOMER_ADDRESS`.
- Removed `craft\commerce\services\Customers::EVENT_BEFORE_SAVE_CUSTOMER`.
- Removed `craft\commerce\services\Customers::SESSION_CUSTOMER`.
- Removed `craft\commerce\services\Customers::consolidateOrdersToUser()`.
- Removed `craft\commerce\services\Customers::deleteCustomer()`.
- Removed `craft\commerce\services\Customers::forgetCustomer()`.
- Removed `craft\commerce\services\Customers::getAddressIds()`.
- Removed `craft\commerce\services\Customers::getCustomer()`.
- Removed `craft\commerce\services\Customers::getCustomerById()`.
- Removed `craft\commerce\services\Customers::getCustomerByUserId()`.
- Removed `craft\commerce\services\Customers::getCustomerId()`.
- Removed `craft\commerce\services\Customers::getCustomersQuery()`.
- Removed `craft\commerce\services\Customers::purgeOrphanedCustomers()`.
- Removed `craft\commerce\services\Customers::saveAddress()`.
- Removed `craft\commerce\services\Customers::saveCustomer()`.
- Removed `craft\commerce\services\Customers::saveUserHandler()`.
- Removed `craft\commerce\services\Discounts::EVENT_BEFORE_MATCH_LINE_ITEM`. `EVENT_DISCOUNT_MATCHES_LINE_ITEM` can be used instead.
- Removed `craft\commerce\services\Discounts::getOrderConditionParams()`. `$order->toArray()` can be used instead.
- Removed `craft\commerce\services\Discounts::populateDiscountRelations()`.
- Removed `craft\commerce\services\Orders::cartArray()`. `toArray()` can be used instead.
- Removed `craft\commerce\services\Payments::getTotalAuthorizedForOrder()`.
- Removed `craft\commerce\services\Payments::getTotalAuthorizedOnlyForOrder()`. `craft\commerce\elements\Order::getTotalAuthorized()` can be used instead.
- Removed `craft\commerce\services\Payments::getTotalPaidForOrder()`. `craft\commerce\elements\Order::getTotalPaid()` can be used instead.
- Removed `craft\commerce\services\Payments::getTotalRefundedForOrder()`.
- Removed `craft\commerce\services\Sales::populateSaleRelations()`.
- Removed `craft\commerce\services\States`.
