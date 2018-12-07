# Release Notes for Craft Commerce 2.x 3.1 support

### Added
- Added project configuration support for Craft Commerce.
- Added `craft\commerce\services\OrderStatuses::archiveOrderStatusById().`
- `craft\commerce\services\Emails` now fires the `beforeSaveEmail`, `afterSaveEmail`, `beforeDeleteEmail` and `afterDeleteEmail` events.
- Added `cract\commerce\services\ProductTypes::getProductTypesByShippingCategoryId().`
- Added `cract\commerce\services\ProductTypes::getProductTypesByTaxCategoryId().`

### Changed
- Order statuses are now archived instead of being deleted.
- Product types no longer can select applicable shipping categories. Instead, shipping categories select applicable product types.
- Product types no longer can select applicable tax categories. Instead, tax categories select applicable product types.

### Fixed
- Fixed a bug where handles and names for archived gateways were not freed up for re-use. ([#485](https://github.com/craftcms/commerce/issues/485))

### Removed
- Removed `craft\commerce\services\OrderStatuses::deleteOrderStatusById().`
- Removed `craft\commerce\services\OrderSettings.`
- Removed `craft\commerce\models\OrderSettings.`
- Removed `craft\commerce\records\OrderSettings.`

