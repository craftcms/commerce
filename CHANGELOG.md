# Release Notes for Craft Commerce

## Unreleased

- Fixed a PHP error that occurred when retrieving a discount via a null coupon code. ([#3045](https://github.com/craftcms/commerce/issues/3045))
- Fixed a bug that could cause a large number of shipping rule category queries.
- Fixed a PHP error that occurred when re-saving all products that had never finished propagating to all sites. ([#1954](https://github.com/craftcms/commerce/issues/1954))
- Fixed a PHP error that would occur when calling `craft\commerce\services\ProductTypes::getEditableProductTypes()` while not logged in. 
- Fixed a PHP error that occurred when saving an invalid shipping method.
- Added support for searching orders by customer name. ([#3050](https://github.com/craftcms/commerce/issues/3050))
- Fixed a bug where 'Enabled for customers to select during checkout' did not correctly support environment variables. ([#3052](https://github.com/craftcms/commerce/issues/3052))

## 4.2.4 - 2022-11-29

- The “Customer” order condition rule now supports orders with no customer.

## 4.2.3 - 2022-11-23

- Fixed a bug where saving an invalid tax category failed silently. ([#3013](https://github.com/craftcms/commerce/issues/3013))
- Fixed a bug where using the `autoSetNewCartAddresses` config setting was getting applied for guest carts.
- Fixed an error that could occur when purging inactive carts.
- Fixed an bug where products and variants weren’t always available as link options in Redactor. ([#3041](https://github.com/craftcms/commerce/issues/3041))

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
