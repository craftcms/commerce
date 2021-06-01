# Release Notes for Craft Commerce

## Unreleased 3.3.3

### Added
- Added the `url` field to the `ProductInterface` GraphQL type.
- Added the `productCount` and `variantCount` queries for fetching the element counts to the GraphQL API. ([#1411](https://github.com/craftcms/commerce/issues/1411))
- Added the ability to sort by SKU on the Products index page. ([#2167](https://github.com/craftcms/commerce/issues/2167))

### Fixed
- Fixed a bug where it was not possible to use the `DefineAttributeKeywordsEvent` event for Product SKU’s. ([#2142](https://github.com/craftcms/commerce/issues/2142))
- Fixed a bug that could occur when opening the “Add all to sale” modal multiple times on the Edit Product page. ([#2146](https://github.com/craftcms/commerce/issues/2146))
- Fixed an error that could occur if MySQL timezones weren’t configured. ([#2163](https://github.com/craftcms/commerce/issues/2163))
- Fixed a PHP error that could occur when validating a Product. ([#2138](https://github.com/craftcms/commerce/issues/2138))
