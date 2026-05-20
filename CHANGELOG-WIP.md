# WIP Release Notes for Craft Commerce 5.7

### Extensibility
- Added `craft\commerce\controllers\OrdersController::actionReassign()`.
- Added `craft\commerce\controllers\OrdersController::actionReassignModal()`.
- Added `craft\commerce\controllers\OrdersController::actionRemoveCustomerData()`.
- Added `craft\commerce\controllers\OrdersController::actionRemoveCustomerDataModal()`.
- Added `craft\commerce\controllers\SubscriptionsController::actionDeleteSubscriptions()`.
- Added `craft\commerce\controllers\SubscriptionsController::actionDeleteSubscriptionsModal()`.
- Added `craft\commerce\elements\Order::getCustomerDeleted()`.
- Added `craft\commerce\elements\Order::setCustomerDeleted()`.
- Added `craft\commerce\elements\deletionblockers\OrderCustomersDeletionBlocker`.
- Added `craft\commerce\elements\deletionblockers\SubscriptionCustomersDeletionBlocker`.
- Added `craft\commerce\services\Orders::reassignOrders()`.
- Added `craft\commerce\services\Orders::removeCustomerData()`.

### System
- Craft Commerce now requires Craft CMS 5.10.0 or later.
- Craft Commerce now requires `ibericode/vat` 2.0 or later.

## Development

- Product permissions have been refined into separate "View", "Create", "Save", and "Delete" permissions.

## Extensibility

- Added `craft\commerce\services\ProductTypes::getViewableProductTypes()`.
- Added `craft\commerce\services\ProductTypes::getViewableProductTypeIds()`.
- Added `craft\commerce\services\ProductTypes::getCreatableProductTypeIds()`.
- Deprecated `craft\commerce\services\ProductTypes::hasPermission()`. Use `$user->can()` directly instead.
- Deprecated `craft\commerce\services\ProductTypes::getEditableProductTypes()`. Use `getViewableProductTypes()` instead.
- Deprecated `craft\commerce\services\ProductTypes::getEditableProductTypeIds()`. Use `getViewableProductTypeIds()` instead.
