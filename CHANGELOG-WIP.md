# WIP Release Notes for Craft Commerce 5.7

### Store Management

- Order edit screens now show notices to store administrators if there was a fluke with the order, such as a coupon/discount’s total usage being exceeded or inventory dropping below zero.
- Added the “Contains Purchasables” order condition rule. ([#4242](https://github.com/craftcms/commerce/issues/4242)) 
- Added the “Has Admin Notices” order condition rule.
- Added deletion blockers for users with existing orders or subscriptions.
- The “Share cart…” order element action now generates a secure tokenized URL.
- The inventory screen now has a “View” menu for showing/hiding table columns. ([#4193](https://github.com/craftcms/commerce/pull/4193))

### Administration

- Craft Commerce user permissions are now organized into conceptual groups.
- Product permissions have been split into separate “View”, “Create”, “Save”, and “Delete” permissions.
- Added the `loadCartUrlExpiry` setting, for controlling how long cart load links remain valid (seven days by default).

### Development

- The `commerce/cart/get-cart` action now supports a `peek` param, which returns cart info without creating a new cart or setting cookies. ([#4263](https://github.com/craftcms/commerce/pull/4263))
- The `commerce/cart/load-cart` action now returns JSON responses for `application/json` requests.

### Extensibility

- Added `craft\commerce\base\ShippingMethod::clearMatchingShippingRuleCache()`.
- Added `craft\commerce\controllers\CartController::actionCartChallenge()`.
- Added `craft\commerce\controllers\CartController::actionCartSent()`.
- Added `craft\commerce\controllers\CartController::actionEmailChallenge()`.
- Added `craft\commerce\controllers\OrdersController::actionGetLoadCartUrl()`.
- Added `craft\commerce\controllers\OrdersController::actionGetShippingMethodOptions()`.
- Added `craft\commerce\controllers\OrdersController::actionReassign()`.
- Added `craft\commerce\controllers\OrdersController::actionReassignModal()`.
- Added `craft\commerce\controllers\OrdersController::actionRemoveCustomerData()`.
- Added `craft\commerce\controllers\OrdersController::actionRemoveCustomerDataModal()`.
- Added `craft\commerce\controllers\SubscriptionsController::actionDeleteSubscriptions()`.
- Added `craft\commerce\controllers\SubscriptionsController::actionDeleteSubscriptionsModal()`.
- Added `craft\commerce\db\Table::CATALOG_PRICING_QUEUE`.
- Added `craft\commerce\elements\Order::getAdminNotices()`.
- Added `craft\commerce\elements\Order::getCustomerDeleted()`.
- Added `craft\commerce\elements\Order::hasAdminNotices()`.
- Added `craft\commerce\elements\Order::hasPurchasables()`.
- Added `craft\commerce\elements\Order::hasPurchasables()`.
- Added `craft\commerce\elements\Order::setCustomerDeleted()`.
- Added `craft\commerce\elements\conditions\orders\ContainsPurchasablesConditionRule`.
- Added `craft\commerce\elements\conditions\orders\HasAdminNoticesConditionRule`.
- Added `craft\commerce\elements\db\OrderQuery::$containsPurchasables`.
- Added `craft\commerce\elements\db\OrderQuery::$containsPurchasables`.
- Added `craft\commerce\elements\db\OrderQuery::$hasAdminNotices`.
- Added `craft\commerce\elements\db\OrderQuery::containsPurchasables()`.
- Added `craft\commerce\elements\db\OrderQuery::containsPurchasables()`.
- Added `craft\commerce\elements\db\OrderQuery::hasAdminNotices()`.
- Added `craft\commerce\elements\deletionblockers\OrderCustomersDeletionBlocker`.
- Added `craft\commerce\elements\deletionblockers\SubscriptionCustomersDeletionBlocker`.
- Added `craft\commerce\enums\ContainsPurchasablesMatch`.
- Added `craft\commerce\enums\ContainsPurchasablesMatch`.
- Added `craft\commerce\enums\OrderNoticeType`.
- Added `craft\commerce\events\PaymentCurrencyRateEvent`.
- Added `craft\commerce\models\OrderNotice::$noticeType`.
- Added `craft\commerce\models\Settings::$loadCartUrlExpiry`.
- Added `craft\commerce\records\CatalogPricingQueue`.
- Added `craft\commerce\services\Carts::getLoadCartUrl()`.
- Added `craft\commerce\services\Carts::peekCart()`.
- Added `craft\commerce\services\CatalogPricing::deleteCatalogPricingQueueRowById()`.
- Added `craft\commerce\services\CatalogPricing::releaseCatalogPricingQueueRowById()`.
- Added `craft\commerce\services\CatalogPricing::reserveCatalogPricingQueueRow()`.
- Added `craft\commerce\services\Orders::reassignOrders()`.
- Added `craft\commerce\services\Orders::removeCustomerData()`.
- Added `craft\commerce\services\PaymentCurrencies::EVENT_DEFINE_PAYMENT_CURRENCY_RATE`.
- Added `craft\commerce\services\PaymentCurrencies::getRateFor()`.
- Added `craft\commerce\services\ProductTypes::getCreatableProductTypeIds()`.
- Added `craft\commerce\services\ProductTypes::getViewableProductTypeIds()`.
- Added `craft\commerce\services\ProductTypes::getViewableProductTypes()`.
- Added `craft\commerce\services\ShippingRuleCategories::getAllShippingRuleCategoriesData()`.
- `craft\commerce\elements\Order::clearNotices()` now has a `$noticeTypes` argument.
- `craft\commerce\elements\Order::getLoadCartUrl()` now returns a secure tokenized URL.
- `craft\commerce\elements\Order::getNotices()` no longer returns admin notices. Use `getAdminNotices()` instead.
- `craft\commerce\elements\Subscription::getSubscriber()` now returns `?User` instead of `User`.
- Deprecated `craft\commerce\services\ProductTypes::getEditableProductTypeIds()`. `getViewableProductTypeIds()` should be used instead.
- Deprecated `craft\commerce\services\ProductTypes::getEditableProductTypes()`. `getViewableProductTypes()` should be used instead.
- Deprecated `craft\commerce\services\ProductTypes::hasPermission()`. `$user->can()` should be used instead.

### System

- The `commerce/cart/cart-challenge` and `commerce/downloads/pdf-challenge` actions are now rate-limited based on IP.
- Improved the performance of shipping method and rule matching.
- Improved the performance of catalog pricing queue jobs.
- Cart load URLs are now generated with time-limited security tokens, which are now required when loading carts from non-authenticated requests.
- `craft\commerce\services\Carts::getCart()` now ensures the cart can always be recalculated. ([#4332](https://github.com/craftcms/commerce/issues/4332#issuecomment-4966010281))
- Craft Commerce now requires Craft CMS 5.10.0 or later.
- Craft Commerce now requires `ibericode/vat` 2.0 or later.
- Craft Commerce now supports `dompdf/dompdf` 3.x, in addition to 2.x.
- Fixed a bug where variants with `{id}` in their Variant Title Format weren’t always getting created with the correct generated title. ([#4308](https://github.com/craftcms/commerce/pull/4308))
