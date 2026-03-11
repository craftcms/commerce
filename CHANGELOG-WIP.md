# WIP Release notes for Commerce 5.6

### Development
- Cart controller actions that accept an explicit cart number are now rate limited to mitigate enumeration attacks.
- Cart numbers are now generated using a cryptographically secure random number generator.
- Shipping rule categories are now eager loaded on shipping rules automatically. ([#4220](https://github.com/craftcms/commerce/issues/4220))

### Extensibility
- Added `craft\commerce\elements\db\ProductQuery::$savable`.
- Added `craft\commerce\elements\db\ProductQuery::savable()`.
- Added `craft\commerce\elements\db\VariantQuery::$savable`.
- Added `craft\commerce\elements\db\VariantQuery::editable()`.
- Added `craft\commerce\elements\db\VariantQuery::savable()`.
- Added `craft\commerce\filters\CartNumberRateLimit`.
- Added `craft\commerce\helpers\ProductQuery::cleanseQueryCriteria()`.
- Added `craft\commerce\services\ShippingRuleCategories::getShippingRuleCategoriesByRuleIds()`.
- Added `craft\commerce\services\ShippingRuleCategories::getShippingRuleCategoriesByRuleIds()`.
- Added `relatedToProducts` and `relatedToVariants` GraphQL query arguments, enabling queries for elements related to specific products or variants. ([#4202](https://github.com/craftcms/commerce/discussions/4202))
- Added `variantUiLabelFormat` and `productUiLabelFormat` settings to product types, for customizing how products and variants are labeled throughout the control panel. ([#4178](https://github.com/craftcms/commerce/pull/4178))
- `craft\commerce\elements\db\ProductQuery::$editable` is now nullable.
- `craft\commerce\elements\db\VariantQuery::$editable` is now nullable.

### System
- Craft Commerce now requires Craft CMS 5.9.9 or later.
- Fixed a bug where Variant with empty SKUs didn't show a validation error when saving a product after it was duplicated. ([#4197](https://github.com/craftcms/commerce/issues/4197))
- Fixed a SQL error that could occur when querying for unfulfilled orders on PostgreSQL. ([#4228](https://github.com/craftcms/commerce/issues/4228))
- Fixed an error that could occur when resaving variants. ([#4226](https://github.com/craftcms/commerce/issues/4226))
- Fixed [high-severity](https://github.com/craftcms/cms/security/policy#severity--remediation) SQL injection vulnerabilities in the control panel. (GHSA-r54v-qq87-px5r)
