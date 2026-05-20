# WIP Release Notes for Craft Commerce 5.7

## System

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
