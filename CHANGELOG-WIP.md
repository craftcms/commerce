# Release Notes for Craft Commerce 5.4 (WIP)

### Store Management

- It is now possible to set a variant’s status from the Product Edit screen. ([#3953](https://github.com/craftcms/commerce/discussions/3953))
- Coupons that are not valid due to order, customer, or address conditions will now return relevent explaination for coupon disqualification within the coupon error. ([#3935](https://github.com/craftcms/commerce/issues/3935))
- Shipping methods and shipping rules now support flexible customer matching, based on a customer condition. ([#3925](https://github.com/craftcms/commerce/issues/3925))
- It is now possible to set a variant’s status from the Product Edit screen. ([#3953](https://github.com/craftcms/commerce/discussions/3953))
- Added an Order condition builder to gateways. ([#3913](https://github.com/craftcms/commerce/discussions/3913))
- Custom metadata is now displayed on the Catalog Pricing Rule Edit screen. ([#3975](https://github.com/craftcms/commerce/pull/3975))
- Added Promotional Price column to product index.

### Development
- Line item snapshots now contain the `catalogPricingRuleId` field. ([#3910](https://github.com/craftcms/commerce/issues/3910))
- Added the `localized` GraphQL product query field. ([#3783](https://github.com/craftcms/commerce/discussions/3783))

### Extensibility
- Added `craft\commerce\base\Gateway::getConfig()`.
- Added `craft\commerce\base\Gateway::getOrderCondition()`.
- Added `craft\commerce\base\Gateway::hasOrderCondition()`.
- Added `craft\commerce\base\Gateway::setOrderCondition()`.
- Added `craft\commerce\base\Purchasable::$catalogPricingRuleId`.
- Added `craft\commerce\base\Purchasable::getCatalogPricingRule()`.
- Added `craft\commerce\base\Gateway::setOrderCondition()`.
- Added `craft\commerce\base\Gateway::getOrderCondition()`.
- Added `craft\commerce\elements\Product::$defaultBasePromotionalPrice`
- Added `craft\commerce\elements\conditions\customers\ShippingMethodCustomerCondition`.
- Added `craft\commerce\elements\conditions\customers\ShippingRuleCustomerCondition`.
- Added `craft\commerce\models\ShippingMethod::getCustomerCondition()`.
- Added `craft\commerce\models\ShippingMethod::setCustomerCondition()`.
- Added `craft\commerce\models\ShippingRule::getCustomerCondition()`.
- Added `craft\commerce\models\ShippingRule::setCustomerCondition()`.

### System
- Added the `resave/variants` command.
- Fixed a bug where gateway settings weren’t storing project config values consistently. ([#3941](https://github.com/craftcms/commerce/issues/3941))
- Fixed a bug where new line items did not expose their submitted quantity to the `craft\commerce\services\LineItems::EVENT_POPULATE_LINE_ITEM` event. ([#3883](https://github.com/craftcms/commerce/issues/3883))
- Craft Commerce no longer requires `ibericode/vat`.