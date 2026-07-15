# WIP Release Notes for Craft Commerce 5.7

### Store Management

- Order notices now have admin notices that appear to store administrators on the Order edit screen. 
- Orders can be filtered by admin notice presence via a new “Has Admin Notices” condition rule on the order index.
- An admin notice is now added to an order when a discount’s total usage limit is exceeded during order completion (e.g. due to concurrent orders).
- An admin notice is now added to an order when a coupon’s max uses is exceeded during order completion.
- An admin notice is now added to an order when available inventory for a purchasable goes below zero during order completion, if the purchasable does not allow out-of-stock purchases.
- Added a “Contains Purchasables” order condition rule, which supports “any”, “all”, and “only” match modes. ([#4242](https://github.com/craftcms/commerce/issues/4242))
- When deleting a user with orders or subscriptions, a modal window is shown alerting the user of potential issues.
- The “Share cart…” order element action now generates a secure tokenized URL.
- The inventory screen now has a "View" menu for showing and hiding columns. Column preferences are saved per-user and per-location. ([#4193](https://github.com/craftcms/commerce/pull/4193))

### Administration

- Craft Commerce user permissions are now organized into conceptual groups.
- Product permissions have been refined into separate “View”, “Create”, “Save”, and “Delete” permissions.
- Added the `loadCartUrlExpiry` setting, for controlling how long cart load links remain valid (default: 7 days).
- Added a new `commerce_cart_recovery` system message for customizing cart recovery emails.

### Development

- Added a `peek` param to the `cart/get-cart` controller action, which returns the existing cart for the current request without creating a new cart or setting cookies — useful for cached pages such as a header cart badge, where `Set-Cookie` responses should be avoided. ([#4263](https://github.com/craftcms/commerce/pull/4263))
- `commerce/cart/load-cart` now returns JSON responses for `application/json` requests, including a `challengeUrl` on failure.
- Improved the performance of shipping method and rule matching.
- Improved the performance of catalog pricing queue jobs.

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
- Added `craft\commerce\events\PaymentCurrencyRateEvent`.
- Added `craft\commerce\enums\OrderNoticeType`.
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
- Deprecated `craft\commerce\services\ProductTypes::getEditableProductTypeIds()`. Use `getViewableProductTypeIds()` instead.
- Deprecated `craft\commerce\services\ProductTypes::getEditableProductTypes()`. Use `getViewableProductTypes()` instead.
- Deprecated `craft\commerce\services\ProductTypes::hasPermission()`. Use `$user->can()` directly instead.
- `craft\commerce\elements\Order::clearNotices()` now has a `$noticeTypes` argument.
- `craft\commerce\elements\Order::getLoadCartUrl()` now returns a secure tokenized URL.
- `craft\commerce\elements\Order::getNotices()` no longer returns admin notices. Use `getAdminNotices()` instead.
- `craft\commerce\elements\Subscription::getSubscriber()` now returns `?User` instead of `User`.

### System

- IP-based rate limiting is now applied to the `commerce/cart/cart-challenge` and `commerce/downloads/pdf-challenge` controller actions.
- Craft Commerce now requires Craft CMS 5.10.0 or later.
- Craft Commerce now supports `dompdf/dompdf` 3.x, in addition to 2.x.
- Craft Commerce now requires `ibericode/vat` 2.0 or later.
- Cart load URLs are now generated with time-limited security tokens, requiring a valid token or authenticated cart ownership to load a cart.
- Getting a cart from the carts service now ensures the cart can always be recalculated. ([#4332](https://github.com/craftcms/commerce/issues/4332#issuecomment-4966010281))
- Fixed a bug where variants with `{id}` in their Variant Title Format weren’t always getting created with the correct generated title. ([#4308](https://github.com/craftcms/commerce/pull/4308))
