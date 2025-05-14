# Release Notes for Craft Commerce 5.4 (WIP)

### Store Management
- It is now possible to set a variant’s status from the Product Edit screen. ([#3953](https://github.com/craftcms/commerce/discussions/3953))
- Added an Order condition builder to gateways. ([#3913](https://github.com/craftcms/commerce/discussions/3913))
- Custom metadata is now displayed on the Catalog Pricing Rule Edit screen. ([#3975](https://github.com/craftcms/commerce/pull/3975))
- Added Promotional Price column to product index.

### Extensibility
- Added `craft\commerce\base\Gateway::setOrderCondition()`.
- Added `craft\commerce\base\Gateway::getOrderCondition()`.
- Added `craft\commerce\base\Gateway::getConfig()`.
- Added `craft\commerce\base\Gateway::hasOrderCondition()`.
- Added `craft\commerce\elements\Product::$defaultBasePromotionalPrice`

### System
- Added the `resave/variants` command.
- Fixed a bug where gateway settings weren’t storing project config values consistently. ([#3941](https://github.com/craftcms/commerce/issues/3941))
