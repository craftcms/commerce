# Release Notes for Craft Commerce 3.x

## Unreleased

### Added
- Added ability to create and edit orders from the control panel.
- Added the ability to send any email from the order edit page.
- Added the ability to download PDFs from the order edit page.
- Added “Edit Orders” and “Delete Orders” user permissions.
- Added the `resave/products` command.
- Added `craft\commerce\controllers\LineItemStatuses`.
- Added `craft\commerce\models\LineItem::adminNote` for order editors to record notes on line items.
- Added `craft\commerce\elements\Order::origin` to know whether the order originated from the web, CP, or an API request.
- Added `craft\commerce\elements\db\OrderQuery::origin()` order query param.
- Added `craft\commerce\elements\Order::recalculationMode` to determine how the order should recalculate.
- Added `craft\commerce\models\LineItem::lineItemStatusId`.
- Added `craft\commerce\records\Purchasable::description`.
- Added `craft\commerce\records\LineItemStatus`.
- Added `craft\commerce\services\Emails::getAllEnabledEmails()`.
- Added `craft\commerce\services\LineItemStatuses`.
- Added the `commerce/orders/newOrder` CP controller action.
- Added `craft\commerce\queue\jobs\CartPurgeJob`.
- Added `craft\commerce\services\lineItemStatuses::EVENT_DEFAULT_LINE_ITEM_STATUS`.
- Added the `yii\behaviors\AttributeTypecastBehavior` to orders, line items, order adjustments, order statuses, and shipping methods.

## Changed
- The order edit page is now a Vue.js app. This will break any template hooks that use javascript to modify the DOM on that page.
- The `craft\commerce\controller\BaseFronEndController::cartArray()` now uses `$cart->toArray()` instead of a custom formatter.
- The donation amount now defaults to zero if no `donationAmount` line item option is not submitted when adding the donation to the cart.

## Deprecated
- Moved the original cart array formatter (used for a cart‘s JSON representation) to `craft\commerce\services\Orders::cartArray($cart)` and deprecated it. Use `$cart->toArray()` instead.