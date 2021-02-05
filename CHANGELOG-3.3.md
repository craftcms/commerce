# Release Notes for Craft Commerce

## Unreleased 3.3

### Added
- It’s now possible to set dynamic condition formulas on shipping rules. ([#1959](https://github.com/craftcms/commerce/issues/1959))
- It’s now possible to include the discounted order value when calculating the order total conditions in a shipping rule. ([#1948](https://github.com/craftcms/commerce/pull/1948))
- All products are now resaved when a product types available tax or shipping categories change. ([#1933](https://github.com/craftcms/commerce/pull/1933))

### Changed
- All front end controllers now use the `number` param for passing the order number. ([#1970](https://github.com/craftcms/commerce/issues/1970))