# Release Notes for Craft Commerce 2.x 3.1 support

### Added
- Added project configuration support for gateways.
- Added project configuration support for product types.
- Added project configuration support for order field layout.
- Added `craft\commerce\services\OrderStatuses::archiveOrderStatusById().`

### Changed
- Order statuses are now archived instead of being deleted.

### Fixed
- Fixed a bug where handles and names for archived gateways were not freed up for re-use. ([#485](https://github.com/craftcms/commerce/issues/485))

### Removed
- Removed `craft\commerce\services\OrderStatuses::deleteOrderStatusById().`
- Removed `craft\commerce\services\OrderSettings.`
- Removed `craft\commerce\models\OrderSettings.`
- Removed `craft\commerce\records\OrderSettings.`

