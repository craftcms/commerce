# Running release notes for Craft Commerce 3.1

### Added
- Added a new condition condition to discounts that allows a condition formula to be used.
- Added variants GraphQL queries. 
- States can now be re-ordered. ([#1284](https://github.com/craftcms/commerce/issues/1284))
- Added `availableForPurchase` to product GraphQL fields and arguments.
- Added `defaultPrice` to product GraphQL fields and arguments.
- Added `craft\commerce\base\TaxEngineInterface`
- Added `craft\commerce\controllers\AddressesController::getCustomerAddress`.
- Added `craft\commerce\elements\Order::getAvailableShippingMethodsOptions()`.
- Added `craft\commerce\elements\Variant::getGqlTypeName()`.
- Added `craft\commerce\elements\Variant::gqlScopesByContext()`.
- Added `craft\commerce\elements\Variant::gqlTypeNameByContext()`.
- Added `craft\commerce\engines\TaxEngine`
- Added `craft\commerce\gql\arguments\elements\Variant`.
- Added `craft\commerce\gql\arguments\interfaces\Variant`.
- Added `craft\commerce\gql\arguments\queries\Variant`.
- Added `craft\commerce\gql\arguments\resolvers\Variant`.
- Added `craft\commerce\gql\arguments\types\elements\Variant`.
- Added `craft\commerce\gql\arguments\types\generators\VariantType`.
- Added `craft\commerce\models\ShippingMethodOption`
- Added `craft\commerce\services\Taxes`
- Ajax requests to `commerce/cart/*` actions will now get a `availableShippingMethodsOptions` key in the response JSON.

### Changed
- The amount of base discount in a discount action is now spread across the line items from highest priced to lowest.
- A line item‘s `price` and `salePrice` are now rounded before being multiplied by the quantity.
- Discount and Tax calculations are now more accurate.
- Updated the example templates
- The commerceCurrency twig filter now rounds consistently with currency rounding
- Fixed an error that could occur when querying product GraphQL using product type. 

### Fixed
- Fixed a bug where it was possible to refund more than the remaining amount of a transaction. ([#1098](https://github.com/craftcms/commerce/issues/1098))
- Fixed a bug where incorrect results could be returned when using the `dateUpdated` order query parameter. ([#1345](https://github.com/craftcms/commerce/issues/1345))
- Fixed a PHP error on the Edit Order page that could occur when viewing an order with a deleted customer. ([#1347](https://github.com/craftcms/commerce/issues/1347))
- Fixed an error that could occur when entering localized numbers on Shipping Rules. ([#1332](https://github.com/craftcms/commerce/issues/1332))
- Fixed a bug that could occur when editing localized decimal prices on Discounts. ([#1174](https://github.com/craftcms/commerce/issues/1174))
