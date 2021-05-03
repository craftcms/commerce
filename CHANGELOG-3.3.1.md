# Release Notes for Craft Commerce

## Unreleased 3.3.1

### Added
- Added `craft\commerce\services\Purchasables::EVENT_PURCHASABLE_AVAILABLE`.
- Added `craft\commerce\services\Purchasables::isPurchasableAvailable()`.

### Changed
- Order condition forumlas now include serialized custom field values for use in formulas. ([#2066]https://github.com/craftcms/commerce/issues/2066))

### Fixed
- Fixed a PHP error that occurred when after changing a variant from having unlimited stock. ([#2111](https://github.com/craftcms/commerce/issues/2111))
- Fixed a PHP error that occurred when when using the `registerUserOnOrderComplete` parameter on the `commerce/cart/complete` action.
- Fixed a bug where the address IDs appeared `null` in the `afterCompleteOrder` event when a registered user checked out as a guest.  