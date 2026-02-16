# WIP Release notes for Commerce 5.6

### Development
- Shipping rule categories are now eager loaded on shipping rules automatically. ([#4220](https://github.com/craftcms/commerce/issues/4220))

### Extensibility
- Added `craft\commerce\services\ShippingRuleCategories::getShippingRuleCategoriesByRuleIds()`.
- Added `craft\commerce\helpers\ProductQuery::cleanseQueryCriteria()`.

### System
- Craft Commerce now requires Craft CMS 5.9.9 or later.
- Fixed a SQL error that could occur when querying for unfulfilled orders on PostgreSQL. ([#4228](https://github.com/craftcms/commerce/issues/4228))
- Fixed an error that could occur when resaving variants. ([#4226](https://github.com/craftcms/commerce/issues/4226))
- Fixed [high-severity](https://github.com/craftcms/cms/security/policy#severity--remediation) SQL injection vulnerabilities in the control panel.
