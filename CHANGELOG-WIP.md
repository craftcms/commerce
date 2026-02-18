# WIP Release notes for Commerce 5.6

- Shipping rule categories are now eager loaded on shipping rules automatically. ([#4220](https://github.com/craftcms/commerce/issues/4220))
- Added `craft\commerce\services\ShippingRuleCategories::getShippingRuleCategoriesByRuleIds()`.
- Cart controller actions that accept an explicit cart number are now rate limited to mitigate enumeration attacks.
- Cart numbers are now generated using a cryptographically secure random number generator.