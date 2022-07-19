# Release Notes for Craft Commerce 4.1 (WIP)

### Added
- Added a new "Unit price" taxable subject option to Tax Rates. ([#2883](https://github.com/craftcms/commerce/pull/2883))
- Added the ability to show the total revenue widget by “Total” or “Total Paid”. ([#2852](https://github.com/craftcms/commerce/issues/2852))
- Added `craft\commerce\services\Customers::transferCustomerData()`. ([#2801](https://github.com/craftcms/commerce/pull/2801))
- Added the `commerce/transfer-customer-data` command.
- Added `craft\commerce\elements\Order::EVENT_BEFORE_APPLY_ADD_NOTICE`. [#2676](https://github.com/craftcms/commerce/issues/2676)
- Added `craft\commerce\elements\Order::hasMatchingAddresses()`.
- Added `craft\commerce\stats\TotalRevenue::TYPE_TOTAL`.
- Added `craft\commerce\stats\TotalRevenue::TYPE_TOTAL_PAID`.
- Added `craft\commerce\stats\TotalRevenue::$type`.
- Added `craft\commerce\widgets\TotalRevenue::$type`.

### Changed
- Addresses submitted to the cart are now validated. ([#2874](https://github.com/craftcms/commerce/pull/2874))
- Garbage collection now removes any orphaned variants, as well as partial donation, order, product, subscription, and variant data.
- `craft\commerce\elements\Product` now supports the `EVENT_DEFINE_CACHE_TAGS` event.
- `craft\commorce\elements\Variant` now supports the `EVENT_DEFINE_CACHE_TAGS` event.

### Fixed
- Fixed a bug where it was possible to save an order with the same address IDs. ([#2841](https://github.com/craftcms/commerce/issues/2841))
- Fixed a bug where order addresses were not being saved with the “live” scenario.
- Fixed a PHP error that occurred when editing a subscription with custom fields.
- Fixed a bug that occurred when setting `autoSetCartShippingMethodOption` to `true`. ([#2875](https://github.com/craftcms/commerce/issues/2875))