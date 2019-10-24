# Release Notes for Craft Commerce

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
- It’s now possible to view and create discounts directly from from the Edit Product page.
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

### Fixed
- Fixed a error when requesting a PDF URL in headless mode. ([#1011](https://github.com/craftcms/commerce/pull/1011))

### Changed
- The “Status Email Address” and “From Name” settings now accept environment variables.

### Fixed
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
- Fixed a bug where it wans’t posible to add non-numeric characters to expiry input in the default credit card form. ([#636](https://github.com/craftcms/commerce/issues/636))

## 2.0.2 - 2019-01-23

### Added
- Added the new Craft Commerce Lite example templates folder `templates/buy`, this is in addition to the existing Craft Commerce Pro example templates folder `templates/shop`.

### Fixed
- Fixed a PHP error raised when extending the `craft\commerce\base\ShippingMethod` class. ([#634](https://github.com/craftcms/commerce/issues/634))
- Fixed a PHP error that occurred when viewing an order that used a since-deleted shipping method.

## 2.0.1 - 2019-01-17

### Fixed
- Fixed an issue where the “Total Paid”, “Total Price”, and “Total Shipping Cost” Order index page columns were showing incorrect values. ([#632](https://github.com/craftcms/commerce/issues/632))
- Fixed an issue where custom field validation errors did not show up on the View Order page. ([#580](https://github.com/craftcms/commerce/issues/580))

### Changed
- Renamed the shipping rule condition from “Mimimum order price” to “Minimum order value” which clarifies the condition is based on item value before discounts and tax.
- Renamed the shipping rule condition from “Maximum order price” to “Maximum order value” which clarifies the condition is based on item value before discounts and tax.

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
- It is now possible to accept payments in multiple currencies.
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
- Deprecated the `update` variable in email templates. Use `orderHistory` instead, which returns the same `Commerce_OrderHistoryModel`.

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
- It is now possible to show customers’ and companies’ names on the Orders index page.
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
- It is now possible to delete countries that are in use by tax/shipping zones and customer addresses.
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
- It is now possible to edit Billing and Shipping addresses on the View Order page.
- It is now possible to manually mark orders as complete on the View Order page.
- It is now possible to submit new order payments from the View Order page.
- Edit Product pages now have a “Save as a new product” option in the Save button menu.
- Edit Product pages now list any sales that are associated with the product.
- It is now possible to sort custom order statuses.
- It is now possible to sort custom payment methods.
- It is now possible to soft-delete payment methods.
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
- The “commerce/cartPayment/pay” controller action has been deprecated. Templates should be updated to use “commerce/payments/pay” instead.
- The “commerce/cartPayment/completePayment” controller action has been deprecated. Templates should be updated to use “commerce/payments/completePayment” instead.
- The “withVariant” product criteria parameter has been deprecated. Templates should be updated to use “hasVariant” instead.

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
- It is now more obvious in the UI that you are unable to delete an order status while orders exist with that status.
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
- It is now possible to set payment method settings from craft/config/commerce.php. To do so, have the file return an array with a `'paymentMethodSettings'` key, set to a sub-array that is indexed by payment method IDs, whose sub-values are set to the payment method’s settings (e.g. `return ['paymentMethodSettings' => ['1' => ['apiKey' => getenv('STRIPE_API_KEY')]]];`).
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
- It is now possible to save arbitrary “options” to line items. When the same purchasable is added to the cart twice, but with different options, it will result in two separate line items rather than one line item with a quantity of 2.
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
- It is now possible to create new Tax Zones and Tax Categories directly from the Edit Tax Rate page.

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
- It is now possible to use token billing with some gateways, like Stripe, by passing a `token` POST param to the `cartPay/pay` controller action, so your customers’ credit card info never touches your server.
- It is now possible to access through all custom Order Statuses `craft.commerce.orderStatuses`.
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
