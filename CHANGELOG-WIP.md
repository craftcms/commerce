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
