# Release Notes for Craft Commerce 2.x

## 2.0.0-beta.15 - 2018-12-27

### Changed
- It’s now possible to show subscription dates on subscriptions indexes.
- `craft\commerce\models\Address::toArray()` now supports `country` and `state` being passed to `$extraFields`. 
- `craft\commerce\models\Customer::toArray()` now supports `user`, `email`, `addresses`, `orders`, `subscriptions`, `primaryBillingAddress`, and `primaryShippingAddress` being passed to `$extraFields`.

### Removed
- Removed the `forgive` attribute for subscription payments.

### Fixed
- Fixed a bug where the `craft\commerce\elements\Order::EVENT_AFTER_ORDER_PAID` event would not always be fired. ([#600](https://github.com/craftcms/commerce/issues/600))

## 2.0.0-beta.14 - 2018-12-05

### Added
- Orders now can have custom-formatted, sequential “reference numbers”. ([#184](https://github.com/craftcms/commerce/issues/184))
- Order indexes now have an “Attempted Payments” source.
- Added `craft\commerce\base\Plan::getAllUserSubscriptions()`.
- Added `craft\commerce\elements\db\OrderQuery::$hasTransactions`.
- Added `craft\commerce\elements\db\OrderQuery::hasTransactions()`.

### Fixed
- Fixed a bug where the `craft\commerce\elements\Order::EVENT_AFTER_ORDER_PAID` event would not always be fired. ([#530](https://github.com/craftcms/commerce/issues/530))
- Fixed an error that could occur when accessing the last transaction on a cart. ([#558](https://github.com/craftcms/commerce/issues/558))
- Fixed an SQL error that occurred when saving a new default tax category. ([#560](https://github.com/craftcms/commerce/issues/560))
- Fixed a bug where new addresses submitted to the cart were not factoring into tax calculations until the following order update.
- Fixed a bug where Customer fields could show incorrect subscription statuses. ([#566](https://github.com/craftcms/commerce/issues/566))
- Fixed the default tax zone checkbox label. ([#532](https://github.com/craftcms/commerce/issues/532))
- Fixed a bug where incomplete carts’ Date Paid would show the current date on View Order pages. ([#588](https://github.com/craftcms/commerce/issues/588))
- Fixed a bug where flat-amount sales increased the price instead of decreasing the price when the checkbox “Ignore previous matching sales if this sale matches” was checked.
- Fixed a bug where variant queries ignored the `status` param. ([#380](https://github.com/craftcms/commerce/issues/380))
- Fixed an SQL error that occurred when using the `isPaid` or `isUnPaid` order query params. ([#380](https://github.com/craftcms/commerce/issues/380))
- Fixed a bug where custom field validation errors weren’t visible on View Order pages. ([#580](https://github.com/craftcms/commerce/issues/580))
- Fixed a bug where disabled discounts could still be applied to orders. ([#576](https://github.com/craftcms/commerce/issues/576))
- Fixed a PHP error that occurred when saving a discount with a non-unique coupon code. ([#569](https://github.com/craftcms/commerce/issues/569))
 
## 2.0.0-beta.13.1 - 2018-11-02

### Fixed
- Fixed an error that occurred when viewing the “Charged”, “Refunded”, or “Disputed” sources on Order indexes. ([#550](https://github.com/craftcms/commerce/issues/550))

## 2.0.0-beta.13 - 2018-11-01

### Changed
- `craft\commerce\services\LineItems::resolveLineItem()` no longer accepts `$qty` and `$note` arguments, and is no longer responsible for updating line item quantity.
- `craft\commerce\elements\Subscription::STATUS_ACTIVE` is now set to `'active'` instead of `'live'`.
- `craft\commerce\elements\db\OrderQuery::customer()` no longer accepts a customer ID. Use `customerId()` to filter orders by their customer ID.
- `craft\commerce\elements\db\OrderQuery::gateway()` no longer accepts a gateway ID. Use `gatewayId()` to filter orders by their gateway ID.

### Deprecated
- Deprecated `craft\commerce\eleemnts\db\OrderQuery::updatedAfter()`. `dateUpdated()` should be used instead.
- Deprecated `craft\commerce\eleemnts\db\OrderQuery::updatedBefore()`. `dateUpdated()` should be used instead.
- Deprecated `craft\commerce\eleemnts\db\SubscriptionQuery::subscribedAfter()`. `dateCreated()` should be used instead.
- Deprecated `craft\commerce\eleemnts\db\SubscriptionQuery::subscribedBefore()`. `dateCreated()` should be used instead.

### Removed
- Removed `craft\commerce\elements\db\OrderQuery::$customer`. `customer()` should be used instead.
- Removed `craft\commerce\elements\db\OrderQuery::$gateway`. `gateway()` should be used instead.
- Removed `craft\commerce\elements\db\OrderQuery::$orderStatus`. `orderStatus()` should be used instead.
- Removed `craft\commerce\elements\db\OrderQuery::$user`. `user()` should be used instead.
- Removed `craft\commerce\elements\db\OrderQuery::updatedOn()`. `dateUpdated()` should be used instead.

### Fixed
- Fixed a bug where required custom fields were not getting validated when subscribing to a plan.
- Fixed a bug where order data exporting would not work on PostgreSQL.
- Fixed a bug where subscriptions could not be edited in Control Panel. ([#534](https://github.com/craftcms/commerce/issues/534))
- Fixed a bug where it wasn’t possible to edit a Craft user’s address if the user field layout had a Customer Info field.
- Fixed a bug where the "From Name" setting was being ignored when sending emails.
- Fixed a SQL error that occurred when saving an email on PostgreSQL.
- Fixed an error that occurred when canceling a subscription. ([#541](https://github.com/craftcms/commerce/issues/541))

## 2.0.0-beta.12.1 - 2018-10-19

### Fixed
- Fixed a bug where it wasn’t possible to edit a Craft user’s address if the user field layout had a Customer Info field.

## 2.0.0-beta.12 - 2018-10-18

### Added
- It’s now possible to export orders from the Orders index page as a CSV, ODS, XLS, or XLSX file.
- It’s now possible to set custom field values when creating a new subscription.
- Added `craft\commerce\elements\Order::EVENT_AFTER_ORDER_PAID`, which is triggered after an order is paid or authorized in full.
- Added `craft\commerce\events\SubscriptionSwitchPlansEvent::$parameters`, enabling dynamic configuration of plan parameters.
- Added `craft\commerce\services\Plans::getPlanByUid()`.

### Changed
- Customer Info fields now display users’ subscription information. ([#503](https://github.com/craftcms/commerce/issues/503))
- Simplified subscription statuses, keeping only `live` and `expired`. Information on if/when the subscription was canceled is still available on the subscription object.
- The Subscriptions index now links subscribers to their Edit User page. ([#503](https://github.com/craftcms/commerce/issues/503))
- Renamed `craft\commerce\base\SubscriptionResponseInterface::isScheduledForCancelation()` to `isScheduledForCancellation()`.

### Fixed
- Ajax requests to the `commerce/cart/update-cart` action now include a `success` boolean in the JSON response.
- Fixed a bug where it wasn’t possible to create a subscription without specifying a trial duration. ([#524](https://github.com/craftcms/commerce/issues/524)
- Fixed a bug where it wasn’t possible to edit an expired subscription. ([craftcms/commerce-stripe#30](https://github.com/craftcms/commerce-stripe/issues/30))
- Fixed SQL errors that occurred when saving shipping categories or tax categories if MySQL was running in strict mode.

### Security
- When switching between subscription plans, the target plan and subscription’s UIDs must now be passed instead of their IDs.
- When switching between subscription plans, all form parameters must now also include the target plan’s UID in the hashed parameters.
- When canceling a subscription, all form parameters must now also include the subscription’s UID in the hashed parameters.
- When subscribing to a plan, its UID must now be passed instead of its ID.
- When reactivating a subscription, its UID must now be passed instead of its ID.

## 2.0.0-beta.11 - 2018-09-26

### Added
- Added `craft\commerce\elements\Order::getLastTransaction()`.

### Removed
- Removed `craft\commerce\base\SubscriptionGateway::getSubscriptionFormHtml().`

### Fixed
- Fixed a bug where `commerce/cart/update-cart` requests could return an inaccurate validation error. ([#493](https://github.com/craftcms/commerce/issues/493)
- Fixed a database error that could occur when saving a shipping method. ([#500](https://github.com/craftcms/commerce/issues/500)

### Security
- `commerce/subscription/subscribe` forms now must include the plan’s UID in the hashed `trialDays` parameter.

## 2.0.0-beta.10 - 2018-09-18

### Changed
- The Dummy gateway now supports subscriptions.
- Subscription queries now only return active subscriptions by default.
- Order status messages can now be longer than 255 characters. ([#465](https://github.com/craftcms/commerce/issues/465)
- Renamed `craft\commerce\services\Subscriptions::EVENT_EXPIRE_SUBSCRIPTION` to `EVENT_AFTER_EXPIRE_SUBSCRIPTION`, and the event is now fired after saving the expired subscription data to the database.
- Reduced the chance of unnecessary order validation errors on `commerce/payments/pay` requests.

### Fixed
- Fixed a bug where the `availableForPurchase` product query param was being ignored.
- Fixed a bug that caused sales to incorrectly increase the price of a purchasable when the “Ignore previous matching sales if this sale matches” checkbox was checked.
- Fixed a bug that prevented default products from being deleted. ([#405](https://github.com/craftcms/commerce/issues/405))
- Fixed a bug where existing products weren’t updated correctly when a new site was added.

## 2.0.0-beta.9 - 2018-09-07

### Added
- Added `craft\commerce\adjustments\Discount::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED`.
- Added a new setting to the Manual payment gateway that restricts it to only be used on zero value orders.
- Product queries now have an `availableForPurchase` param.

### Changed
- The `commerce/cart/update-cart` action will now remove items from the cart with a quantity of zero.

### Fixed
- Fixed a bug where store locations could get incorrect validation errors.
- Fixed a bug where only admins were allowed to edit addresses on Edit Order pages.
- Restored missing shipping and tax management permission settings.
- Fixed a bug where variant errors would not show up on Edit Product pages in some cases.
- Fixed a bug where order statuses weren’t remembering whether they were the default status. ([#476](https://github.com/craftcms/commerce/issues/476))
- Fixed a bug where variants with generated SKUs could get incorrect validation errors. ([#451](https://github.com/craftcms/commerce/issues/451))
- Fixed a bug where order PDF URLs weren’t accessible to customers in some cases.
- Fixed a bug where Edit Product pages weren’t revealing which tab(s) had errors on it, if the errors occurred within a Matrix field.

## 2.0.0-beta.8.1 - 2018-08-27

### Fixed
- Fixed a bug where shipping address errors would not show up in `commerce/cart` actions’ JSON responses.
- Fixed a bug where prices were not being displayed in a localized manor, causing price changes when re-saving variants, for some locales.

## 2.0.0-beta.8 - 2018-08-22

### Added
- `commerce/cart` actions’ JSON responses now include any address errors.

### Fixed
- Fixed a CSRF error that could occur when an external gateway tried to call a webhook URL.
- Fixed a SQL error that occurred when saving a tax rate, if MySQL was running in strict mode.
- Fixed a SQL error that could occur when saving a discount.
- Fixed a bug where completed orders could have inaccurate address validation errors. ([#413](https://github.com/craftcms/commerce/issues/413))
- Fixed a bug where orders’ `datePaid` attributes weren’t getting set on order completion.
- Fixed a bug where it wasn’t possible to create a subscription plan when only one gateway was available.
- Fixed a bug where it wasn’t possible to pay for an order in the Control Panel in some cases.

### Security
- Order queries’ `number` parameter must be set to a complete order number now.

## 2.0.0-beta.7 - 2018-08-07

### Added
- The cart can now be retrieved as JSON with the `commerce/cart/get-cart` action.
- A custom PDF can now be attached to any order status email.
- Added the `cp.commerce.order.edit.main-pane` template hook to the Edit Order page.
- Added the `craft\commerce\elements\Variant::EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT` event.
- Added the `craft\commerce\elements\Variant::EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT` event.
- Added the `craft\commerce\elements\Variant::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT` event.
- Added the `craft\commerce\elements\Variant::EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT` event.

### Changed
- A flash notice is now set when the cart is updated successfully. ([#392](https://github.com/craftcms/commerce/issues/392))
- Product and variant custom field data is no longer included in the line item snapshot by default. Use the new snapshot events to manually snapshot field data.
- “New sale” and “New discount” buttons now appear in the page header. ([#408](https://github.com/craftcms/commerce/issues/408))

### Fixed
- Fixed a bug where the Save keyboard shortcut for orders wouldn’t keep the user on the Edit Order page. ([#412](https://github.com/craftcms/commerce/issues/412))
- Fixed a PHP error that occurred if a product variant was saved with a non-numeric price. ([#404](https://github.com/craftcms/commerce/issues/404))
- Fixed a SQL error that occurred if a product type was saved with a non-unique handle. ([#409](https://github.com/craftcms/commerce/issues/409))
- Fixed a SQL error that occurred if a product variant was saved with a non-unique SKU. ([#399](https://github.com/craftcms/commerce/issues/399))
- Fixed an error that occurred when updating statuses on the Orders index page. ([#414](https://github.com/craftcms/commerce/issues/414))
- Fixed a bug where PDFs that referenced remote fonts would fail to generate. ([#393](https://github.com/craftcms/commerce/issues/393))
- Fixed a bug where a product’s `getCpEditUrl()` method could omit the site handle on multi-site installs. ([craftcms/cms#3089](https://github.com/craftcms/cms/issues/3089))
- Fixed a PHP error that occurred when calling the deprecated `craft.commerce.countriesList` template variable.
- Fixed a SQL error that occurred when saving discounts if MySQL was running in strict mode.([#407](https://github.com/craftcms/commerce/issues/407))
- Fixed an error that occurred when updating from Commerce 1 to Commerce 2. ([#423](https://github.com/craftcms/commerce/issues/423))
- Fixed a bug where it was not possible to save a product with a non-integer price.
- Fixed a bug where variants’ custom fields weren’t getting updated when a product was saved somewhere besides the Edit Product page.
- Fixed a VAT ID validation error that occurred when submitting a VAT ID with non-numeric characters. ([#426](https://github.com/craftcms/commerce/issues/426))
- Fixed a bug where validation errors on the Edit Store Location page were not showing up. ([#370](https://github.com/craftcms/commerce/issues/370))
- Fixed a bug where `commerce/cart` actions weren’t including line items’ validation errors in JSON responses. ([#430](https://github.com/craftcms/commerce/issues/430))

## 2.0.0-beta.6 - 2018-06-29

### Changed
- Variant indexes can now have a “Product” column.
- Variant titles now include their product titles.
- Variant queries now have a `price` param.

### Fixed
- Fixed a PHP error that occurred when validating line items.
- Fixed a PHP error that could occur when saving a product without unlimited stock.
- Fixed a bug where clicking on the “Date Created” column header on order and subscription indexes wouldn’t update the sort order.
- Fixed a bug where `commerce\base\PurchasableInterface::getSnapshot()` had the wrong casing.
- Fixed a PHP error that occurred when deleting a primary billing or shipping address.
- Fixed an error that could occur when updating from Commerce 1 to Commerce 2. ([#282](https://github.com/craftcms/commerce/issues/282))
- Fixed a bug where shipping costs defined by shipping categories were not getting applied to the cart correctly. ([#381](https://github.com/craftcms/commerce/issues/381))
- Fixed a PHP error that occurred when saving a new order status.
- Fixed a bug where carts could forget the selected shipping method. ([#387](https://github.com/craftcms/commerce/issues/387))
- Fixed a bug where stock was getting validated when saving a completed order. ([#390](https://github.com/craftcms/commerce/issues/390))
- Fixed a bug where Commerce's Twig extension wasn't getting registered for Commerce emails. ([#397](https://github.com/craftcms/commerce/issues/397))
- Fixed compatibility with the Redactor reference tag links. ([#338](https://github.com/craftcms/commerce/issues/338))
- Fixed a bug where empty new carts were being saved to the database unnecessarily. ([#403](https://github.com/craftcms/commerce/issues/403))

## 2.0.0-beta.5 - 2018-05-30

### Added
- Products now have an “Available for purchase” checkbox, making it possible to have a live product that isn’t available for purchase yet. ([#345](https://github.com/craftcms/commerce/issues/345))
- Added the `craft\commerce\services\ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS` event.

### Changed
- `commerce/cart/update-cart` can now update [multiple line items](https://github.com/craftcms/commerce-docs/blob/v2/en/adding-to-and-updating-the-cart.md#updating-line-items) at once. ([#357](https://github.com/craftcms/commerce/issues/357))
- Commerce no longer uses `Omnipay\Common\Helper` for credit card number verification. ([#344](https://github.com/craftcms/commerce/issues/344))
- `craft\commerce\base\GatewayInterface::createPaymentSource()` now requires an userId parameter.

### Deprecated
- Deprecated the `commerce/cart/update-line-item` action. (`commerce/cart/update-cart` can be used instead.)
- Deprecated the `commerce/cart/remove-line-item` action. (`commerce/cart/update-cart` can be used instead.)
- Deprecated the `commerce/cart/remove-all-line-items` action. (`commerce/cart/update-cart` can be used instead.)

### Fixed
- Fixed a bug where `commerce/cart/update-cart` requests could clear all custom field values. ([#347](https://github.com/craftcms/commerce/issues/347))
- Fixed a PHP error that occurred during an upgrade migration when custom purchasable types were in use.
- Fixed an issue where Commerce’s element types weren’t getting registered with Craft. ([#352](https://github.com/craftcms/commerce/issues/352))
- Fixed a bug where the first variant on Edit Product pages couldn’t be set to disabled. ([#343](https://github.com/craftcms/commerce/issues/343))
- Fixed a bug where stock-checking rules weren’t taking new line items into account. ([#343](https://github.com/craftcms/commerce/issues/343))
- Fixed a bug where category shipping rule prices were getting saved with the incorrect category. ([#323](https://github.com/craftcms/commerce/issues/323))
- Fixed a PHP error that occurred when completing an order with a coupon code that had a per-user usage limit. ([#354](https://github.com/craftcms/commerce/issues/354))

## 2.0.0-beta.4.1 - 2018-05-09

### Fixed
- Fixed changelog version typo

## 2.0.0-beta.4 - 2018-05-09

### Added
- Added the ability to place a note on a refund transaction.
- Added `craft\commerce\services\TaxCategories::getAllTaxCategoriesAsList()`.

### Fixed
- Fixed a JavaScript error on the Edit Product Type page.
- Fixed a bug where the state was not saving correctly on the Store Location settings page.
- Fixed a bug where line items with zero quantity were not ignored when adding multiple items to the cart. ([#330](https://github.com/craftcms/commerce/issues/330))
- Fixed a bug where variants weren't getting saved in the user-defined order on the Edit Product page. ([#337](https://github.com/craftcms/commerce/issues/337))
- Fixed a bug where zero-value shipping adjustments were getting added to line items when only a base rate existed.
- Fixed a bug where `craft\commerce\elements::getDefaultVariant()` was not returning the default variant.

## 2.0.0-beta.3 - 2018-04-17

### Added
- Added the `craft\commerce\elements\Order::EVENT_AFTER_ADD_LINE_ITEM` event.

### Fixed
- Fixed a bug where variant fields did not appear on the Edit Product page if no product fields existed. ([#317](https://github.com/craftcms/commerce/issues/317))
- Fixed a bug where subscription payment details were not being syntax-highlighted.
- Fixed a PHP error that occurred when saving the primary payment currency while using PostgreSQL.
- Fixed a bug where trial status was being incorrectly reported by subscriptions.
- Fixed a bug where it was impossible to pay with a stored payment source.

## 2.0.0-beta.2 - 2018-04-10

### Added
- Added `craft\commerce\elements\db\VariantQuery::hasSales()`.

### Fixed
- Fixed a bug on the Edit Order page where the info buttons on line items were unresponsive. ([#297](https://github.com/craftcms/commerce/issues/297))
- Fixed a bug where customer addresses were not editable from the Edit User page. ([#315](https://github.com/craftcms/commerce/issues/315))
- Fixed a PHP error that occurred when submitting a payment source at checkout. ([#313](https://github.com/craftcms/commerce/issues/313))
- Fixed a PHP error that occurred when submitting a gateway choice at checkout. ([#312](https://github.com/craftcms/commerce/issues/312))
- Fixed a PHP error that occurred when calling `count()` on a variant query. 
- Fixed a PHP 7.0 compatibility issue. ([#305](https://github.com/craftcms/commerce/issues/305))
- Fixed a PHP 7.2 compatibility issue. ([#308](https://github.com/craftcms/commerce/issues/308))

## 2.0.0-beta.1.3 - 2018-04-05

### Fixed
- Fixed a PHP error that occurred when purging abandoned carts without an email.

## 2.0.0-beta.1.2 - 2018-04-05

### Changed
- Updating from Commerce 1.x now requires that Commerce 1.2.1360 or greater is installed.

### Fixed
- Fixed an SQL error that occurred when updating from Commerce 1.x that had existing discounts. ([#299](https://github.com/craftcms/commerce/issues/299))
- Fixed currency editing template where it was calling deprecated methods ([#303](https://github.com/craftcms/commerce/issues/303))

## 2.0.0-beta.1.1 - 2018-04-04

### Fixed
- Fixed a bug where visiting the payment currencies settings page could result in fatal error on new installs.
- Fixed a SQL error that occurred when installing Commerce.
- Fixed a SQL error that occurred when updating from Commerce 1.x.

## 2.0.0-beta.1 - 2018-04-04

### Added
- Added 'Copy' Reference tag to Product actions.
- Added the possibility for users to save payment sources.
- Added subscriptions features for gateways that support them.
- Added `craft\commerce\services\PaymentSources` service.
- Added `craft\commerce\services\Plans` service.
- Added `craft\commerce\services\Subscriptions` service.
- Added additional ways for sales to affect the price of matching products.
- Added `paidStatus` attribute to the Order element.
- Added `craft.variants` twig variable which returns the new VariantQuery class.
- Added `craft.subscriptions` twig variable which returns the new SubscriptionQuery class.
- Added the ability for any purchasable to have sales.
- Added ability to have sales applied based on a Craft category related to a purchasable.
- Added `craft\commerce\models\Customer::getPrimaryBillingAddress()`
- Added `craft\commerce\models\Customer::getPrimaryShippingAddress()`
- Added `craft\commerce\services\Address::getAddressByIdAndCustomerId()`
- Added `craft\commerce\services\Customers::setLastUsedAddresses()`

### Changed
- Removed the `cartCookieDuration` config item. All carts are now related to php session. 
- Replaced`customer.lastUsedShippingAddress` and `customer.lastUsedBillingAddress` with `customer.primaryBillingAddress` and `customer.primaryShippingAddress`
- Removed `baseShipping`, `baseDiscount`, `baseTax`, `baseTaxIncluded` attributes from the order model. Order's now have order level adjustments.
- Removed `shipping`, `discount`, `tax`, `taxIncluded` attributes from the line item model. Line item's now have line item level adjustments.
- The Shipping Rule interface now expects a shipping category ID passed to each rate method.
- `paymentMethodSettings` setting is now called `gatewaySettings` and it now uses handles to reference gateways instead of IDs.
- `Payment Methods` are now called `Gateways` and this is reflected across the entire plugin and it's API.
- `sendCartInfoToGateways` is now called `sendCartInfo` and is a per-gateway setting.
- `requireEmailForAnonymousPayments` config setting removed as completed order now always require the correct email address to make anonymous payments on orders.
- `Variant::setSalesApplied()` and `Variant::getSalesApplied()` is now called `Variant::setSales()` and `Variant::getSales()` respectively.
- `OrderAdjustment::optionsJson` is now called `OrderAdjustment::sourceSnapshot`.
- Removed the purchasable interface `PurchasableInterface::validateLineItem()`. Your purchasables should now use `PurchasableInterface::getLineItemRules()` to add validation rules to line items. 
- The payment method overrides in commerce.php config file have been moved to a commerce-gateway.php config file.
- Vat ID validation is now using the MIT licenced dannyvankooten/vat.php
- The `Variants::EVENT_PURCHASE_VARIANT` event has been replaced by `ElementInterface::afterOrderComplete($lineItem)`
- `craft\commerce\services\Cart` is now `craft\commerce\services\Carts`
- `craft\commerce\services\Carts::addToCart()` now requires a `craft\commerce\models\LineItem` object as the second parameter.
- `craft\commerce\services\LineItems::getLineItemByOrderPurchasableOptions()` is now `craft\commerce\services\LineItems::resolveLineItem()`
- `craft\commerce\services\Pdf::pdfForOrder()` is now `craft\commerce\services\Pdf::renderPdfForOrder()`
- Last addresses used by customers are no longer stored. Instead, customers have primary shipping and billing addresses.
- `craft\commerce\elements\Orders` now fires the following events: `beforeCompleteOrder`, and `afterCompleteOrder`.
- `craft\commerce\services\Addresses` now fires the following events: `beforeSaveAddress`, and `afterSaveAddress`.
- `craft\commerce\services\Carts` now fires the following events: `beforeAddToCart`, `afterAddToCart`, `afterRemoveFromCart` and a cancelable `beforeRemoveFromCart` event.
- `craft\commerce\services\Discounts` now fires the cancelable `beforeMatchLineItem` event.
- `craft\commerce\services\Emails` now fires the following events: `afterSendEmail`, and a cancelable `beforeSendEmail`.
- `craft\commerce\services\LineLitems` now fires the following events: `beforeSaveLineItem`, `afterSaveLineItem`, `createLineItem`, and `populateLineItem`.
- `craft\commerce\services\OrderHistories` now fires the `orderStatusChange` event.
- `craft\commerce\services\Payments` now fires the following events: `beforeCaptureTransaction`, `afterCaptureTransaction`, `beforeRefundTransaction`, `afterRefundTransaction`, `afterProcessPaymentEvent` and a cancelable `beforeProcessPaymentEvent` event.
- `craft\commerce\services\PaymentSources` now fires the following events: `deletePaymentSource`, `beforeSavePaymentSource` and `afterSavePaymentSource`events.
- `craft\commerce\services\Plans` fires the following events: `archivePlan`, `beforeSavePlan` and `afterSavePlan`events.
- `craft\commerce\services\Purchasables` fires the `registerPurchasableElementTypes` event.
- `craft\commerce\services\Sales` now fires the cancelable `beforeMatchPurchasableSale` event.
- `craft\commerce\services\Subscriptions` fires the `expireSubscription`, `afterCreateSubscription`, `afterReactivateSubscription`, `afterSwitchSubscriptionPlan`, `afterCancelSubscription`, `beforeUpdateSubscription`, `receiveSubscriptionPayment` and cancelable `beforeCreateSubscription`, `beforeReactivateSubscription`, `beforeSwitchSubscriptionPlan` and `beforeCancelSubscription` events.
- `craft\commerce\services\Transactions` now fires the `afterSaveTransaction` event.
- `craft\commerce\services\Variants` now fires the `purchaseVariant` event.
- Instead of the `commerce_modifyEmail` hook you should use the cancelable `beforeSendEmail` event fired by `craft\commerce\services\Emails`.
- Instead of the `commerce_registerOrderAdjusters` hook you should use the `registerOrderAdjusters` event fired by `craft\commerce\services\OrderAdjustments`.
- To register new gateway types, use the `registerGatewayTypes` event fired by `craft\commerce\services\Gateways`.
- The `commerce_modifyOrderSources`, `commerce_getOrderTableAttributeHtml`, `commerce_getProductTableAttributeHtml`, `commerce_defineAdditionalOrderTableAttributes`, `commerce_defineAdditionalProductTableAttributes` hooks have been replaced by more generic Craft 3 hooks.

### Removed
- Removed `craft\commerce\services\Countries::getCountryByAttributes()`
- Removed `craft\commerce\services\States::getStatesByAttributes()`
- Removed `craft\commerce\models\Customer::getLastUsedBillingAddress()`
- Removed `craft\commerce\models\Customer::getLatUsedShippingAddress()`
- Removed the `commerce_modifyGatewayRequestData`, `commerce_modifyGatewayRequestData` and `commerce_modifyItemBag` hooks.
