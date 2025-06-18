# Release Notes for Craft Commerce 5.4 (WIP)

### Store Management
- It’s now possible to set variants’ statuses from Edit Product screens. ([#3953](https://github.com/craftcms/commerce/discussions/3953))
- Coupon validation errors now provide an explanation when invalid due to order, customer, or address conditions. ([#3935](https://github.com/craftcms/commerce/issues/3935))
- Catalog Pricing Rule screens now display custom metadata. ([#3975](https://github.com/craftcms/commerce/pull/3975))
- Product indexes now have a “Promotional Price” column.

### Administration
- It’s now possible to set a variants’ default “Promotable”, “Track Inventory”, “Allow out of stock purchases”, and “Available for purchase” values when configuring variant field layouts. ([#3571](https://github.com/craftcms/commerce/issues/3571))
- Shipping methods and shipping rules now support flexible customer matching, based on a customer condition. ([#3925](https://github.com/craftcms/commerce/issues/3925))
- Gateways now have a “Match Order” condition that determines which orders the gateway should be available for. ([#3913](https://github.com/craftcms/commerce/discussions/3913))
- All native order attributes are now available as card attributes. ([#4019](https://github.com/craftcms/commerce/issues/4019))
- Added the `resave/variants` command.

### Development
- Line item snapshots now contain a `catalogPricingRuleId` field. ([#3910](https://github.com/craftcms/commerce/issues/3910))
- Added the `localized` field to products’ GraphQL data. ([#3783](https://github.com/craftcms/commerce/discussions/3783))

### Extensibility
- Added `craft\commerce\base\Gateway::getConfig()`.
- Added `craft\commerce\base\Gateway::getOrderCondition()`.
- Added `craft\commerce\base\Gateway::getOrderCondition()`.
- Added `craft\commerce\base\Gateway::hasOrderCondition()`.
- Added `craft\commerce\base\Gateway::setOrderCondition()`.
- Added `craft\commerce\base\Gateway::setOrderCondition()`.
- Added `craft\commerce\base\Purchasable::$catalogPricingRuleId`.
- Added `craft\commerce\base\Purchasable::getCatalogPricingRule()`.
- Added `craft\commerce\controllers\OrdersController::actionCopyAddressToUser()`.
- Added `craft\commerce\elements\Product::$defaultBasePromotionalPrice`
- Added `craft\commerce\elements\conditions\customers\ShippingMethodCustomerCondition`.
- Added `craft\commerce\elements\conditions\customers\ShippingRuleCustomerCondition`.
- Added `craft\commerce\models\ShippingMethod::getCustomerCondition()`.
- Added `craft\commerce\models\ShippingMethod::setCustomerCondition()`.
- Added `craft\commerce\models\ShippingRule::getCustomerCondition()`.
- Added `craft\commerce\models\ShippingRule::setCustomerCondition()`.

### System
- Improved store query performance. ([#4029](https://github.com/craftcms/commerce/issues/4029))
- Fixed a bug where the purchasable cache was not cleared when stock was updated.
- Fixed a PHP error that could occur when sending emails. ([#4017](https://github.com/craftcms/commerce/issues/4017))
- Fixed a SQL error that could occur when upgrading to Commerce 5. ([#4044](https://github.com/craftcms/commerce/issues/4044))
- Fixed a bug where duplicate order references could be generated. ([#4050](https://github.com/craftcms/commerce/issues/4050))
- Fixed a bug where purchasables’ `shippingCategoryId` and `taxCategoryId` properties couldn’t be set via `setAttributes()`. ([#4046](https://github.com/craftcms/commerce/issues/4046))
- Fixed a bug where gateway settings weren’t storing project config values consistently. ([#3941](https://github.com/craftcms/commerce/issues/3941))
- Fixed a bug where new line items did not expose their submitted quantity to the `craft\commerce\services\LineItems::EVENT_POPULATE_LINE_ITEM` event. ([#3883](https://github.com/craftcms/commerce/issues/3883))
