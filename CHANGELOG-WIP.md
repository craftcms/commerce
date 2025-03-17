# Release Notes for Craft Commerce 5.4 (WIP)

- Added an Order condition builder to gateways. ([#3913](https://github.com/craftcms/commerce/discussions/3913))
- Added `craft\commerce\base\Gateway::setOrderCondition()`.
- Added `craft\commerce\base\Gateway::getOrderCondition()`.
- Added `craft\commerce\base\Gateway::getConfig()`.
- Added `craft\commerce\base\Gateway::hasOrderCondition()`.

- Fixed a bug where gateway settings weren’t storing project config values consistently. ([#3941](https://github.com/craftcms/commerce/issues/3941))