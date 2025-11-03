# WIP Release Notes for Craft Commerce

### Store Management
- It is now possible to suppress order emails when marking an order as complete in the control panel. ([#4144](https://github.com/craftcms/commerce/issues/4144))

### Administration
- Added billing and shipping address conditions to gateways. ([#4100](https://github.com/craftcms/commerce/pull/4100))
- Added preview targets for products. ([#4128](https://github.com/craftcms/commerce/pull/4128))
- Added slug translation options to product types. ([#4088](https://github.com/craftcms/commerce/pull/4088))
- Gateway condition rules now allow multiple gateways to be selected. ([#4112](https://github.com/craftcms/commerce/issues/4112))

### Development
- Added the `--force` option to the `commerce/reset-data` command. ([#4115](https://github.com/craftcms/commerce/discussions/4115))

### Extensibility
- Added `\craft\commerce\services\Discounts::appendCouponCode()`. ([#4084](https://github.com/craftcms/commerce/pull/4084))
- Added `\craft\commerce\events\InventoryMovementEvent`. ([#4063](https://github.com/craftcms/commerce/pull/4063))
- Added `\craft\commerce\events\UpdateInventoryLevelEvent`. ([#4063](https://github.com/craftcms/commerce/pull/4063))
- Added `\craft\commerce\services\Inventory::EVENT_AFTER_EXECUTE_UPDATE_INVENTORY_LEVEL`. ([#4063](https://github.com/craftcms/commerce/pull/4063))
- Added `\craft\commerce\services\Inventory::EVENT_AFTER_EXECUTE_INVENTORY_MOVEMENT`. ([#4063](https://github.com/craftcms/commerce/pull/4063))

### System
- Fixed a bug where purchasables could have a shipping category that was no longer available to their product type. ([#4018](https://github.com/craftcms/commerce/issues/4018))
