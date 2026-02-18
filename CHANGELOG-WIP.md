# WIP Release notes for Commerce 5.6

- Fixed a bug where Variant with empty SKUs didn't show a validation error when saving a product after it was duplicated. ([#4197](https://github.com/craftcms/commerce/issues/4197))
- Shipping rule categories are now eager loaded on shipping rules automatically. ([#4220](https://github.com/craftcms/commerce/issues/4220))
- Added `craft\commerce\services\ShippingRuleCategories::getShippingRuleCategoriesByRuleIds()`.
- Added `variantUiLabelFormat` and `productUiLabelFormat` settings to product types, for customizing how products and variants are labeled throughout the control panel. ([#4178](https://github.com/craftcms/commerce/pull/4178))
- Added `relatedToProducts` and `relatedToVariants` GraphQL query arguments, enabling queries for elements related to specific products or variants. ([#4202](https://github.com/craftcms/commerce/discussions/4202))
