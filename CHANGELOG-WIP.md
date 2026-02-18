# WIP Release notes for Commerce 5.6

- Shipping rule categories are now eager loaded on shipping rules automatically. ([#4220](https://github.com/craftcms/commerce/issues/4220))
- Added `craft\commerce\services\ShippingRuleCategories::getShippingRuleCategoriesByRuleIds()`.
- Added `variantUiLabelFormat` and `productUiLabelFormat` settings to product types, for customizing how products and variants are labeled throughout the control panel. ([#4178](https://github.com/craftcms/commerce/pull/4178))
- Added `craft\commerce\elements\db\ProductQuery::$savable`.
- Added `craft\commerce\elements\db\ProductQuery::savable()`.
- Added `craft\commerce\elements\db\VariantQuery::$savable`.
- Added `craft\commerce\elements\db\VariantQuery::savable()`.
- Added `craft\commerce\elements\db\VariantQuery::editable()`.
- `craft\commerce\elements\db\ProductQuery::$editable` is now nullable.
- `craft\commerce\elements\db\VariantQuery::$editable` is now nullable.
