# Release Notes for Craft Commerce

## Unreleased 3.3.3

### Added
- Added the `url` field to the `ProductInterface` GraphQL type.

### Fixed
- Fixed an error when validating a Product without a shipping category. ([#2138](https://github.com/craftcms/commerce/issues/2138))
- Fixed a bug where it was not possible to use the `DefineAttributeKeywordsEvent` event for Product SKU’s. ([#2142](https://github.com/craftcms/commerce/issues/2142))
- Fixed a bug that could occur when opening the “Add all to sale” modal multiple times on the Edit Product page. ([#2146](https://github.com/craftcms/commerce/issues/2146))