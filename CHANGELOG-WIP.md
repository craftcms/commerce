# Release Notes for Craft Commerce 4.1 (WIP)

### Added
- Added `craft\commerce\elements\Order::hasMatchingAddresses()`.

### Changed
- `craft\commerce\elements\Product` now supports the `EVENT_DEFINE_CACHE_TAGS` event.
- `craft\commorce\elements\Variant` now supports the `EVENT_DEFINE_CACHE_TAGS` event.

### Fixed
- Fixed a bug where it was possible to save an order with the same address IDs. ([#2841](https://github.com/craftcms/commerce/issues/2841))
