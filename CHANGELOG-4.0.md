# Release Notes for Craft Commerce 4

## Unreleased

### Added
- Added `craft\commerce\controllers\DiscountsController::_saveCoupons()`.
- Added `craft\commerce\controllers\DiscountsController::_setCouponsOnDiscount()`.
- Added `craft\commerce\controllers\DiscountsController::actionGenerateCoupons()`.
- Added `craft\commerce\models\Coupon`.
- Added `craft\commerce\models\Discount::$_coupons`.
- Added `craft\commerce\models\Discount::$couponFormat`.
- Added `craft\commerce\models\Discount::getCoupons()`.
- Added `craft\commerce\models\Discount::setCoupons()`.
- Added `craft\commerce\plugin\Services::getCoupons()`.
- Added `craft\commerce\records\Coupon`.
- Added `craft\commerce\services\Coupons`.
- Added `craft\commerce\validators\CouponValidator`.
- Added `craft\commerce\web\assets\coupons\CouponsAsset`.
- Customers are now User elements.
- Discounts can now have a condition builder to allow flexible matching of the order, user, and adresses. ([#2290](https://github.com/craftcms/commerce/discussions/2290))
- Shipping zones now use a condition builder to determine whether an address is within a zone.
- Tax zones now use a condition builder to determine whether an address is within a zone.
- Added `\craft\commerce\services\Customers::savePrimaryBillingAddressId()`
- Added `\craft\commerce\services\Customers::savePrimaryShippingAddressId()`
- Added `craft\commerce\base\Zone`.
- Added `craft\commerce\behaviors\CustomerBehavior`.
- Added `craft\commerce\controllers\DiscountsController::DISCOUNT_COUNTER_TYPE_EMAIL`.
- Added `craft\commerce\controllers\DiscountsController::DISCOUNT_COUNTER_TYPE_TOTAL`.
- Added `craft\commerce\controllers\DiscountsController::DISCOUNT_COUNTER_TYPE_USER`.
- Added `craft\commerce\controllers\OrdersController::actionCreateCustomer()`.
- Added `craft\commerce\controllers\OrdersController::actionGetCustomerAddresses()`.
- Added `craft\commerce\elements\Order::$sourceBillingAddressId`
- Added `craft\commerce\elements\Order::$sourceShippingAddressId`
- Added `craft\commerce\controllers\OrdersController::actionGetOrderAddress()`.
- Added `craft\commerce\controllers\OrdersController::actionValidateAddress()`.
- Added `craft\commerce\services\Discounts::clearUserUsageHistoryById()`.
- Added `craft\commerce\services\Discounts::getUserUsageStatsById()`.
- Added `craft\commerce\models\OrderHistory::$userId`.
- Added `craft\commerce\models\OrderHistory::getUser()`.
- Added `craft\commerce\models\Store`.
- Added `craft\commerce\records\OrderHistory::$userId`.
- Added `craft\commerce\records\OrderHistory::getUser()`.
- Added `craft\commerce\services\Discounts::clearUserUsageHistoryById()`.
- Added `craft\commerce\services\Discounts::getUserUsageStatsById()`.

### Changed
- Craft Commerce now requires Craft CMS 4.0.0-alpha.1 or newer.
- It’s now possible to create an order for a user from the Edit User page.
- Tax rate input fields no longer require the percent symbol.
- `craft\commerce\models\TaxRate::getRateAsPercent()` now returns a localized value.
- Ajax responses from `commerce/payment-sources/*` no longer include `paymentForm`. Use `paymentFormErrors` instead.
- `craft\commerce\elements\Products::getVariants()`, `getDefaultVariant()`, `getChepeastVariant()`, `getTotalStock()`, and `getHasUnlimitedStock()` now return data related to only enabled variant(s) by default.
- Renamed `craft\commerce\elements\Order::EVENT_AFTER_REMOVE_LINE_ITEM` string from `afterRemoveLineItemToOrder` -> `afterRemoveLineItemFromOrder.
- `craft\commerce\model\ProductType::$titleFormat` was renamed to `$variantTitleFormat`.
- `craft\commerce\services\Variants::getAllVariantsByProductId()` now accepts a third param `$includeDisabled`.
- `craft\commerce\services\LineItems::createLineItem()` no longer has an `$orderId` argument.
- `craft\commerce\services\LineItems::resolveLineItem()` expects an `$order` argument instead of `$orderId`.
- `craft\commerce\models\TaxAddressZone::setCountries()` now expects an array of country code strings.
- `craft\commerce\models\TaxAddressZone::setStatues()` now expects an array of state codes or state name strings.
- `craft\commerce\services\Addresses::addressWithinZone()` is now found in `craft\commerce\helpers\AddressZone::addressWithinZone()`.

### Changed (Previously Deprecated)
- The `cartUpdatedNotice` param is no longer accepted for `commerce/cart/*` requests. Use a hashed `successMessage` param instead.
- Renamed `craft\commerce\services\ShippingMethods\getAvailableShippingMethods()` to `getMatchingShippingMethods()` to better represent the method.
- Subscription plans are no longer accessible via old Control Panel URLs.
- Renamed “Customer” column to “User” on Order indexes.
- Removed `craft\commerce\models\ProductType::lineItemFormat`.

### Deprecated
- Deprecated `craft\commerce\models\Address::getCountryText()`. Use `getCountryName()` instead.
- Deprecated `craft\commerce\models\Address::getStateText()`. Use `getStateName()` instead.
- Deprecated `craft\commerce\models\Address::getAbbreviationText()`. Use `getStateAbbreviation()` instead.
- Deprecated `craft\commerce\models\ShippingAddressZone::getStatesNames()`. Use `getStatesLabels()` instead.
- Deprecated `craft\commerce\services\Plans::getAllGatewayPlans()`. Use `getPlansByGatewayId()` instead.
- Deprecated `craft\commerce\services\Subscriptions::getSubscriptionCountForPlanById()`. Use `getSubscriptionCountByPlanId()` instead.
- Deprecated `craft\commerce\services\Subscriptions::doesUserHaveAnySubscriptions()`. Use `doesUserHaveSubscriptions()` instead.
- Deprecated `craft\commerce\services\TaxRates::getTaxRatesForZone()`. Use `getTaxRatesByTaxZoneId()` instead.
- Deprecated `craft\commerce\services\Transactions::deleteTransaction()`. Use `deleteTransactionById()` instead.

### Removed (Changed in 4.0, not previously deprecated)
- Removed `\craft\commerce\records\Discount::CONDITION_USER_GROUPS_ANY_OR_NONE`. Discount user groups were migrated to the customer condition rule.
- Removed `\craft\commerce\records\Discount::CONDITION_USER_GROUPS_INCLUDE_ALL`. Discount user groups were migrated to the customer condition rule.
- Removed `\craft\commerce\records\Discount::CONDITION_USER_GROUPS_INCLUDE_ANY`. Discount user groups were migrated to the customer condition rule.
- Removed `\craft\commerce\records\Discount::CONDITION_USER_GROUPS_EXCLUDE`. Discount user groups were migrated to the customer condition rule.
- Removed `\craft\commerce\models\Discount::getUserGroupIds()`. Discount user groups were migrated to the customer condition rule.
- Removed `\craft\commerce\models\Discount::setUserGroupIds()`. Discount user groups were migrated to the customer condition rule.
- Removed `craft\commerce\events\DefineAddressLinesEvent`. Use the new `\craft\services\Addresses::formatAddress()` instead.
- Removed `craft\commerce\base\AddressZoneInterface`.
- Removed `craft\commerce\controllers\AddressesController::actionGetCustomerAddresses()`. Use `actionGetUserAddresses()` instead.
- Removed `craft\commerce\controllers\CountriesController`.
- Removed `craft\commerce\controllers\CustomerAddressesController`.
- Removed `craft\commerce\controllers\DiscountsController::DISCOUNT_COUNTER_TYPE_CUSTOMER`. Use `DISCOUNT_COUNTER_TYPE_USER` instead.
- Removed `craft\commerce\controllers\OrdersController::_prepCustomersArray()`. Use `_customerToArray()` instead.
- Removed `craft\commerce\controllers\PlansController::actionRedirect()`.
- Removed `craft\commerce\models\Discount::$code`.
- Removed `craft\commerce\controllers\StatesController`.
- Removed `craft\commerce\elements\Order::removeEstimatedBillingAddress()`. Used `setEstimatedBillingAddress(null)` instead.
- Removed `craft\commerce\elements\Order::removeEstimatedShippingAddress()`. Used `setEstimatedShippingAddress(null)` instead.
- Removed `craft\commerce\events\CustomerAddressEvent`.
- Removed `craft\commerce\events\CustomerEvent`.
- Removed `craft\commerce\models\Country`.
- Removed `craft\commerce\models\OrderHistory::$customerId`. User `$userId` instead.
- Removed `craft\commerce\models\OrderHistory::getCustomer()`. User `getUser()` instead.
- Removed `craft\commerce\models\Settings::$showCustomerInfoTab`. Use `$showEditUserCommerceTab` instead. 
- Removed `craft\commerce\models\States`.
- Removed `craft\commerce\models\TaxAddressZone::getCountryIds()`
- Removed `craft\commerce\models\TaxAddressZone::getStateIds()`
- Removed `craft\commerce\records\Country`.
- Removed `craft\commerce\records\CustomerAddress`. Use `UserAddress` instead.
- Removed `craft\commerce\records\OrderHistory::getCustomer()`. User `getUser()` instead.
- Removed `craft\commerce\records\ShippingZoneCountry`.
- Removed `craft\commerce\records\ShippingZoneState`.
- Removed `craft\commerce\records\States`.
- Removed `craft\commerce\records\TaxZoneCountry`.
- Removed `craft\commerce\records\TaxZoneState`.
- Removed `craft\commerce\services\Addresses`.
- Removed `craft\commerce\services\Countries`.
- Removed `craft\commerce\services\States`.
- Removed `craft\commerce\services\Customers::EVENT_AFTER_SAVE_CUSTOMER_ADDRESS`.  Use `Element::EVENT_AFTER_SAVE`, checking for `$event->sender->ownerId` to determine the user address being saved.
- Removed `craft\commerce\services\Customers::EVENT_AFTER_SAVE_CUSTOMER`.
- Removed `craft\commerce\services\Customers::EVENT_BEFORE_SAVE_CUSTOMER_ADDRESS`.
- Removed `craft\commerce\services\Customers::EVENT_BEFORE_SAVE_CUSTOMER`.
- Removed `craft\commerce\services\Customers::SESSION_CUSTOMER`.
- Removed `craft\commerce\services\Customers::deleteCustomer()`.
- Removed `craft\commerce\services\Customers::forgetCustomer()`.
- Removed `craft\commerce\services\Customers::getAddressIds()`. Use `ArrayHelper::getColumn($user->getAddresses(), 'id')` instead.
- Removed `craft\commerce\services\Customers::getCustomer()`. Use `Craft::$app->getUser()->getIdentity()`
- Removed `craft\commerce\services\Customers::getCustomerByUserId()`.
- Removed `craft\commerce\services\Customers::getCustomersQuery()`.
- Removed `craft\commerce\services\Customers::purgeOrphanedCustomers()`.
- Removed `craft\commerce\services\Customers::saveAddress()`. Use `Craft::$app->getElements()->saveElement()` instead.
- Removed `craft\commerce\services\Customers::saveCustomer()`.
- Removed `craft\commerce\services\Customers::saveUserHandler()`.
- Removed `craft\commerce\services\Discounts::clearCustomerUsageHistoryById()`. Use `clearUserUsageHistoryById()` instead.
- Removed `craft\commerce\services\Discounts::getCustomerUsageStatsById()`. Use `getUserUsageStatsById()` instead.
- Removed `craft\commerce\services\States`.
- Removed direct `moneyphp/money` dependency.

### Removed (Previously Deprecated)
- Removed `Plugin::getInstance()->getPdf()`. Use `Plugin::getInstance()->getPdfs()` instead.
- Removed `availableShippingMethods` from `commerce/cart/*` action JSON responses. Use `availableShippingMethodOptions` instead.
- Removed `commerce/orders/purchasable-search` action. Use `commerce/orders/purchasables-table` instead.
- Removed `craft\commerce\Plugin::t()`. Use `Craft::t('commerce', 'My String')` instead.
- Removed `craft\commerce\base\CustomersController`.
- Removed `craft\commerce\base\OrderDeprecatedTrait`.
- Removed `craft\commerce\elements\Order::getAdjustmentsTotalByType()` has been deprecated. Use `Order::getTotalTax()`, `Order::getTotalDiscount()`, or `Order::getTotalShippingCost()` instead.
- Removed `craft\commerce\elements\Order::getAvailableShippingMethods()` has been deprecated. Use `Order::getAvailableShippingMethodOptions()` instead.
- Removed `craft\commerce\elements\Order::getOrderLocale()` has been deprecated. Use `Order::orderLanguage` instead.
- Removed `craft\commerce\elements\Order::getShippingMethodId()` has been removed. Use `Order::getShippingMethodHandle()` instead.
- Removed `craft\commerce\elements\Order::getShouldRecalculateAdjustments()` has been deprecated. Use `Order::recalculationMode` instead.
- Removed `craft\commerce\elements\Order::getTotalTaxablePrice()`. Taxable price is now calculated within the tax adjuster.
- Removed `craft\commerce\elements\Order::setShouldRecalculateAdjustments()` has been deprecated. Use `Order::recalculationMode` instead.
- Removed `craft\commerce\elements\actions\DeleteOrder`. Using standard `craft\elements\actions\Delete` instead.
- Removed `craft\commerce\elements\actions\DeleteProduct`. Using standard `craft\elements\actions\Delete` instead.
- Removed `craft\commerce\elements\traits\OrderDeprecatedTrait`.
- Removed `craft\commerce\events\LineItemEvent::isValid`.
- Removed `craft\commerce\helpers\Localization::formatAsPercentage()`.
- Removed `craft\commerce\models\Email::getPdfTemplatePath()`. Use `craft\commerce\models\Email::getPdf()->getTemplatePath()` instead.
- Removed `craft\commerce\models\LineItem::getAdjustmentsTotalByType()` has been deprecated. Use `LineItem::getTax()`, `LineItem::getDiscount()`, or `LineItem::getShippingCost()` instead.
- Removed `craft\commerce\models\LineItem::setSaleAmount()`. Sale amount was read only since 3.1.1.
- Removed `craft\commerce\queue\jobs\ConsolidateGuestOrders`.
- Removed `craft\commerce\services\Customers::consolidateOrdersToUser()`.
- Removed `craft\commerce\services\Customers::getCustomer()`.
- Removed `craft\commerce\services\Customers::getCustomerById()`.
- Removed `craft\commerce\services\Customers::getCustomerId()`.
- Removed `craft\commerce\services\Discounts::EVENT_BEFORE_MATCH_LINE_ITEM`. Use `Discounts::EVENT_DISCOUNT_MATCHES_LINE_ITEM` instead.
- Removed `craft\commerce\services\Discounts::populateDiscountRelations()`.
- Removed `craft\commerce\services\Orders::cartArray()`. Use `$order->toArray()` instead.
- Removed `craft\commerce\services\Payments::getTotalAuthorizedForOrder()`.
- Removed `craft\commerce\services\Payments::getTotalAuthorizedOnlyForOrder()`. Use `Order::getTotalAuthorized()` instead.
- Removed `craft\commerce\services\Payments::getTotalPaidForOrder()`. Use `Order::getTotalPaid()` instead.
- Removed `craft\commerce\services\Payments::getTotalRefundedForOrder()`.
- Removed `craft\commerce\services\Sales::populateSaleRelations()`.
- Removed `craft\commmerce\models\LineItem::getAdjustmentsTotalByType()` has been deprecated. Use `LineItem::getTax()`, `LineItem::getDiscount()`, or `LineItem::getShippingCost()` instead.
- Removed `craft\commmerce\models\LineItem::setSaleAmount()`. Sale amount was read only since 3.1.1.
- Removed `json_encode_filtered` twig filter.
- Removed the `orderPdfFilenameFormat` setting.
- Removed the `orderPdfPath` setting.

### Fixed

### Security
