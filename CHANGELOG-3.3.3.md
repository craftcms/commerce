# Release Notes for Craft Commerce

## Unreleased 3.3.3

### Added
- It’s now possible to download collated PDFs from the Orders index page. ([#1785](https://github.com/craftcms/commerce/issues/1785))
- Added the `url` field to the `ProductInterface` GraphQL type.
- Added the `productCount` and `variantCount` queries for fetching the element counts to the GraphQL API. ([#1411](https://github.com/craftcms/commerce/issues/1411))
- Added the ability to sort by SKU on the Products Index page. ([#2167](https://github.com/craftcms/commerce/issues/2167))

### Fixed
- Fixed an error when validating a Product without a shipping category. ([#2138](https://github.com/craftcms/commerce/issues/2138))
- Fixed a bug where it was not possible to use the `DefineAttributeKeywordsEvent` event for Product SKU’s. ([#2142](https://github.com/craftcms/commerce/issues/2142))
- Fixed a bug that could occur when opening the “Add all to sale” modal multiple times on the Edit Product page. ([#2146](https://github.com/craftcms/commerce/issues/2146))