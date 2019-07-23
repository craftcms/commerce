# Release Notes for Craft Commerce 3.x

## Unreleased

### Added
- Added the ability to create and edit orders from the control panel.
- Added the ability to send emails from the order edit page.
- Added “Edit Orders” and “Delete Orders” user permissions.
- Line items now have a status that can be changed on the order edit page.
- Line items now have an admin note attribute.
- Purging inactive carts is now run as a job in the queue.
- Orders now track the origin of where the order was created (web or control panel).
- Orders now have recalculation modes to determine what should be recalculated on the order. 
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
- Added order errors to the `commerce/payments/pay` controller action ajax response under the `orderErrors` JSON key.

## Changed
- The order edit page is now a Vue.js app. This will break any template hooks that use javascript to modify the DOM on that page.
- The `craft\commerce\controller\BaseFronEndController::cartArray()` now uses `$cart->toArray()` instead of the custom formatter.
- If no `donationAmount` line item option parameter is submitted when adding the donation to the cart, the donation will default to zero and not return an error.
- Ajax responses to from the `commerce/payments/pay` controller action has renamed `paymentForm` to `paymentFormErrors`.

## Deprecated
- Moved the original cart array formatter (used for a cart‘s JSON representation) to `craft\commerce\services\Orders::cartArray($cart)` and deprecated it. Use `$cart->toArray()` instead.
- Deprecated `craft\commerce\elements\Order::setShouldRecalculateAdjustments()` and `craft\commerce\elements\Order::getShouldRecalculateAdjustments()`, use `craft\commerce\elements\Order::recalculationMode`  instead.

## Removed
- Removed the `commerce/cart/update-line-item` controller action. (Deprecated in 2.0)
- Removed the `commerce/cart/remove-line-item` controller action. (Deprecated in 2.0)
- Removed the `commerce/cart/remove-all-line-items` controller action. (Deprecated in 2.0)
- Removed `craft.commerce.availableShippingMethods`. (Deprecated in 2.0)
- Removed `craft.commerce.cart`. (Deprecated in 2.0)
- Removed `craft.commerce.countriesList`. (Deprecated in 2.0)
- Removed `craft.commerce.customer`. (Deprecated in 2.0)
- Removed `craft.commerce.discountByCode`. (Deprecated in 2.0)
- Removed `craft.commerce.primaryPaymentCurrency`. (Deprecated in 2.0)
- Removed `craft.commerce.statesArray`. (Deprecated in 2.0)
- Removed `craft\commerce\base\Purchasable::getPurchasableId()`. (Deprecated in 2.1)
- Removed `craft\commerce\elements\Order::getOrderLocale()`. (Deprecated in 2.0)
- Removed `craft\commerce\elements\Order::getTotalTax()`. (Deprecated in 2.0)
- Removed `craft\commerce\elements\Order::getTotalTaxIncluded()`. (Deprecated in 2.0)
- Removed `craft\commerce\elements\Order::getTotalDiscount()`. (Deprecated in 2.0)
- Removed `craft\commerce\elements\Order::getTotalShippingCost()`. (Deprecated in 2.0)
- Removed `craft\commerce\elements\Order::updateOrderPaidTotal()`. (Deprecated in 2.0)
- Removed `craft\commerce\elements\db\OrderQuery::updatedAfter()`. (Deprecated in 2.0)
- Removed `craft\commerce\elements\db\OrderQuery::updatedBefore()`. (Deprecated in 2.0)
- Removed `craft\commerce\elements\Product::getSnapshot()`. (Deprecated in 2.1)
- Removed `craft\commerce\elements\Product::getUnlimitedStock()`. (Deprecated in 2.0)
- Removed `craft\commerce\elements\Variant::getSalesApplied()`. (Deprecated in 2.0)
- Removed `craft\commerce\elements\db\SubscriptionQuery::subscribedBefore()`. (Deprecated in 2.0)
- Removed `craft\commerce\elements\db\SubscriptionQuery::subscribedAfter()`. (Deprecated in 2.0)
- Removed `craft\commerce\models\ShippingMethod::amount`. (Deprecated in 2.0)
- Removed `craft\commerce\models\Discount::setFreeShipping()`. (Deprecated in 2.1)
- Removed `craft\commerce\models\Discount::getFreeShipping()`. (Deprecated in 2.1)
- Removed `craft\commerce\models\LineItem::fillFromPurchasable()`. (Deprecated in 2.0)
- Removed `craft\commerce\models\Order::getTax()`. (Deprecated in 2.0)
- Removed `craft\commerce\models\Order::getTaxIncluded()`. (Deprecated in 2.0)
- Removed `craft\commerce\models\Order::getDiscount()`. (Deprecated in 2.0)
- Removed `craft\commerce\models\Order::getShippingCost()`. (Deprecated in 2.0)
- Removed `craft\commerce\services\Countries::getAllCountriesListData()`. (Deprecated in 2.0)
- Removed `craft\commerce\services\Gateways::getAllFrontEndGateways()`. (Deprecated in 2.0)
- Removed `craft\commerce\services\ShippingMethods::getOrderedAvailableShippingMethods()`. (Deprecated in 2.0)
