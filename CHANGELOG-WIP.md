# Release Notes for Craft Commerce 5.1 (WIP)

## Unreleased 5.1

### Store Management
- Catalog pricing rules now support flexible product and variant matching, based on an product and variant conditions.

### Administration
- Added a new “Manage subscription plans” permission.
- Added a new “Manage donation settings” permission.
- Added a new “Manage store general setting” permission.
- Added a new “Manage payment currencies” permission.

### Development

### Extensibility
- Added `craft\commerce\elements\conditions\products\CatalogPricingRuleProductCondition`.
- Added `craft\commerce\elements\conditions\variants\CatalogPricingRuleVariantCondition`.
- Added `craft\commerce\models\CatalogPricingRule::getProductCondition()`.
- Added `craft\commerce\models\CatalogPricingRule::setProductCondition()`.
- Added `craft\commerce\models\CatalogPricingRule::getVariantCondition()`.
- Added `craft\commerce\models\CatalogPricingRule::setVariantCondition()`.

### System
- Craft Commerce now requires Craft CMS 5.2 or later.