# Running release notes for Craft Commerce 3.1

### Added

- States can now be re-ordered. ([#1284](https://github.com/craftcms/commerce/issues/1284))

### Changed

- A line item‘s `price` and `salePrice` are now rounded before being multiplied by the quantity.
- Discount and Tax calculations are now more accurate.
- The commerceCurrency twig filter now rounds consistently with currency rounding
