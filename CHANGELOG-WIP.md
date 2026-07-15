# WIP Release Notes for Craft Commerce 5.7

### Store Management

- Order notices now support an admin-only type. Admin notices are displayed in a separate red box above customer notices in the CP order edit page, with individual **Clear** buttons (visible in edit mode only). Orders can be filtered by admin notice presence via a new **Has Admin Notices** condition rule on the order index.
- An admin notice is now added to an order when a discount's total usage limit is exceeded during order completion (e.g. due to concurrent orders).
- An admin notice is now added to an order when a coupon's max uses is exceeded during order completion.
- An admin notice is now added to an order when available inventory for a purchasable goes below zero during order completion, if the purchasable does not allow out-of-stock purchases.
- Added a "Contains Purchasables" order condition rule, which supports "any", "all", and "only" match modes. ([#4242](https://github.com/craftcms/commerce/issues/4242))
- When deleting a user with orders or subscriptions, store admins are now presented with actionable options to resolve the blocker (reassign orders, remove customer data, or delete subscriptions), rather than a generic error.
- Anonymous users attempting to load a cart with an expired or missing token are now shown a cart recovery form, which sends a new recovery link to the cart's email address.
- The "Share cart…" order element action now generates a secure tokenized URL.

### Administration

- Product permissions have been refined into separate "View", "Create", "Save", and "Delete" permissions.
- Added the `cartLoadUrlExpiry` setting, for controlling how long cart load links remain valid (default: 7 days).
- Added a new `commerce_cart_recovery` system message for customizing cart recovery emails.
- The inventory screen now has a "View" menu for showing and hiding columns. Column preferences are saved per-user and per-location. ([#4193](https://github.com/craftcms/commerce/pull/4193))
- Craft Commerce user permissions are now organized into conceptual groups.

### Development

- Added a `cart/peek-cart` controller action, which returns the existing cart for the current request without creating a new cart or setting cookies — useful for cached pages such as a header cart badge, where `Set-Cookie` responses should be avoided. ([#4263](https://github.com/craftcms/commerce/pull/4263))
- `commerce/cart/load-cart` now returns JSON responses for `application/json` requests, including a `challengeUrl` on failure.
- Improved the performance of shipping method and rule matching.

### Extensibility

- Added `craft\commerce\elements\Order::getAdminNotices()`.
- Added `craft\commerce\elements\Order::hasAdminNotices()`.
- Added `craft\commerce\elements\conditions\orders\HasAdminNoticesConditionRule`.
- Added `craft\commerce\elements\db\OrderQuery::$hasAdminNotices`.
- Added `craft\commerce\elements\db\OrderQuery::hasAdminNotices()`.
- Added `craft\commerce\models\OrderNotice::NOTICE_TYPE_ADMIN`.
- Added `craft\commerce\models\OrderNotice::NOTICE_TYPE_CUSTOMER`.
- Added `craft\commerce\models\OrderNotice::$noticeType`.
- `craft\commerce\elements\Order::clearNotices()` now has a `$clearAdminNotices` argument (default `false`), which must be passed as `true` to also clear admin notices.
- `craft\commerce\elements\Order::getNotices()` no longer returns admin notices. Use `getAdminNotices()` instead.
- Added `craft\commerce\base\ShippingMethod::clearMatchingShippingRuleCache()`.
- Added `craft\commerce\controllers\CartController::actionCartChallenge()`.
- Added `craft\commerce\controllers\CartController::actionCartSent()`.
- Added `craft\commerce\controllers\CartController::actionEmailChallenge()`.
- Added `craft\commerce\controllers\CartController::actionPeekCart()`.
- Added `craft\commerce\controllers\OrdersController::actionGetLoadCartUrl()`.
- Added `craft\commerce\controllers\OrdersController::actionGetShippingMethodOptions()`.
- Added `craft\commerce\controllers\OrdersController::actionReassign()`.
- Added `craft\commerce\controllers\OrdersController::actionReassignModal()`.
- Added `craft\commerce\controllers\OrdersController::actionRemoveCustomerData()`.
- Added `craft\commerce\controllers\OrdersController::actionRemoveCustomerDataModal()`.
- Added `craft\commerce\controllers\SubscriptionsController::actionDeleteSubscriptions()`.
- Added `craft\commerce\controllers\SubscriptionsController::actionDeleteSubscriptionsModal()`.
- Added `craft\commerce\db\Table::CATALOG_PRICING_QUEUE`.
- Added `craft\commerce\elements\Order::getCustomerDeleted()`.
- Added `craft\commerce\elements\Order::hasPurchasables()`.
- Added `craft\commerce\elements\Order::setCustomerDeleted()`.
- Added `craft\commerce\elements\conditions\orders\ContainsPurchasablesConditionRule`.
- Added `craft\commerce\elements\db\OrderQuery::$containsPurchasables`.
- Added `craft\commerce\elements\db\OrderQuery::containsPurchasables()`.
- Added `craft\commerce\elements\deletionblockers\OrderCustomersDeletionBlocker`.
- Added `craft\commerce\elements\deletionblockers\SubscriptionCustomersDeletionBlocker`.
- Added `craft\commerce\enums\ContainsPurchasablesMatch`.
- Added `craft\commerce\elements\db\OrderQuery::$containsPurchasables`.
- Added `craft\commerce\elements\db\OrderQuery::containsPurchasables()`.
- Added `craft\commerce\elements\Order::hasPurchasables()`.
- Added `craft\commerce\enums\ContainsPurchasablesMatch`.
- Added `craft\commerce\events\PaymentCurrencyRateEvent`, allowing plugins to override a payment currency's exchange rate at the point of use.
- Added `craft\commerce\records\CatalogPricingQueue`.
- Added `craft\commerce\services\Carts::getLoadCartUrl()`.
- Added `craft\commerce\services\Carts::peekCart()`.
- Added `craft\commerce\services\CatalogPricing::reserveCatalogPricingQueueRow()`.
- Added `craft\commerce\services\CatalogPricing::releaseCatalogPricingQueueRowById()`.
- Added `craft\commerce\services\CatalogPricing::deleteCatalogPricingQueueRowById()`.
- Added `craft\commerce\services\Orders::reassignOrders()`.
- Added `craft\commerce\services\Orders::removeCustomerData()`.
- Added `craft\commerce\services\PaymentCurrencies::EVENT_DEFINE_PAYMENT_CURRENCY_RATE`.
- Added `craft\commerce\services\PaymentCurrencies::getRateFor()`.
- Added `craft\commerce\services\ShippingRuleCategories::getAllShippingRuleCategoriesData()`.
- Added `craft\commerce\services\ProductTypes::getCreatableProductTypeIds()`.
- Added `craft\commerce\services\ProductTypes::getViewableProductTypeIds()`.
- Added `craft\commerce\services\ProductTypes::getViewableProductTypes()`.
- `craft\commerce\elements\Order::getLoadCartUrl()` now returns a secure tokenized URL.
- `craft\commerce\elements\Subscription::getSubscriber()` now returns `?User` instead of `User`.
- Deprecated `craft\commerce\services\ProductTypes::getEditableProductTypeIds()`. Use `getViewableProductTypeIds()` instead.
- Deprecated `craft\commerce\services\ProductTypes::getEditableProductTypes()`. Use `getViewableProductTypes()` instead.
- Deprecated `craft\commerce\services\ProductTypes::hasPermission()`. Use `$user->can()` directly instead.

### Fixed

- Fixed a bug where variants with `{id}` in their Variant Title Format weren’t always getting created with the correct generated title. ([#4308](https://github.com/craftcms/commerce/pull/4308))

### System

- Craft Commerce now requires Craft CMS 5.10.0 or later.
- Craft Commerce now supports `dompdf/dompdf` 3.x, in addition to 2.x.
- Craft Commerce now requires `ibericode/vat` 2.0 or later.
- Cart load URLs are now generated with time-limited security tokens, requiring a valid token or authenticated cart ownership to load a cart.
- PDF download URLs now use the `code` query param instead of `token`. Existing URLs with `token` can be redirected via a server rewrite rule (see [upgrade notes](https://gist.github.com/lukeholder/7605ee8dbb0cbde305ba86bc05747315)) ([#4303](https://github.com/craftcms/commerce/issues/4303)).
- Getting a cart from the carts service now ensures the cart can always be recalculated. ([#4332](https://github.com/craftcms/commerce/issues/4332#issuecomment-4966010281))
