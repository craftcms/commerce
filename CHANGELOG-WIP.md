# Release Notes for Craft Commerce 4.1 (WIP)

### Added
- Added `craft\commerce\elements\Order::EVENT_BEFORE_APPLY_ADD_NOTICE`. [#2676](https://github.com/craftcms/commerce/issues/2676)
- Added `craft\commerce\elements\Order::hasMatchingAddresses()`.
- Added `craft\commerce\services\Customers::transferCustomerData()`. ([#2801](https://github.com/craftcms/commerce/pull/2801))
- Added the `commerce/transfer-customer-data` command.

### Changed
- `craft\commerce\elements\Product` now supports the `EVENT_DEFINE_CACHE_TAGS` event.
- `craft\commorce\elements\Variant` now supports the `EVENT_DEFINE_CACHE_TAGS` event.

### Fixed
- Fixed a bug where it was possible to save an order with the same address IDs. ([#2841](https://github.com/craftcms/commerce/issues/2841))
- Fixed a PHP error that occurred when editing a subscription with custom fields.