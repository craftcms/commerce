# Release Notes for Craft Commerce 5.1 (WIP)

## Unreleased 5.1

### Administration
- Added the ability to manage transfers between inventory locations.
- Added a new “Manage subscription plans” permission.
- Added a new “Manage donation settings” permission.
- Added a new “Manage store general setting” permission.
- Added a new “Manage payment currencies” permission.

### Extensibility

- Added `\craft\commerce\controllers\TransfersController`.
- Added `craft\commerce\elements\Transfer`.
- Added `craft\commerce\elements\conditions\transfers\TransferCondition`.
- Added `craft\commerce\elements\db\TransferQuery`.
- Added `craft\commerce\enums\TransferStatusType`.
- Added `craft\commerce\fieldlayoutelements\TransferManagementField`.
- Added `craft\commerce\models\TransferDetail`.
- Added `craft\commerce\record\TransferDetail`.
- Added `craft\commerce\services\Transfers`.
- Added `craft\commerce\services\InventoryLocations::getAllInventoryLocationsAsList`

### System
- Craft Commerce now requires Craft CMS 5.3 or later.