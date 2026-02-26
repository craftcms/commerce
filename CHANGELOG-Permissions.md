# WIP Release notes for Commerce 5.6

### Development
- Product permissions have been refined into separate "View", "Create", "Save", and "Delete" permissions.

### Extensibility
- Added `craft\commerce\services\ProductTypes::getViewableProductTypes()`.
- Added `craft\commerce\services\ProductTypes::getViewableProductTypeIds()`.
- Added `craft\commerce\services\ProductTypes::getCreatableProductTypeIds()`.
- Deprecated `craft\commerce\services\ProductTypes::hasPermission()`. Use `$user->can()` directly instead.