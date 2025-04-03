# Release Notes for Craft Commerce 5.4 (WIP)

### Store Management
- Shipping methods and shipping rules now support flexible customer matching, based on a customer condition. ([#3925](https://github.com/craftcms/commerce/issues/3925))

### Extensibility
- Added `craft\commerce\elements\conditions\customers\ShippingMethodCustomerCondition`.
- Added `craft\commerce\elements\conditions\customers\ShippingRuleCustomerCondition`.
- Added `craft\commerce\models\ShippingMethod::getCustomerCondition()`.
- Added `craft\commerce\models\ShippingMethod::setCustomerCondition()`.
- Added `craft\commerce\models\ShippingRule::getCustomerCondition()`.
- Added `craft\commerce\models\ShippingRule::setCustomerCondition()`.