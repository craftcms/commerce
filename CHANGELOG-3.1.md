# Running release notes for Craft Commerce 3.1

### Added
- Discounts can now have a dynamic condition formula. ([#470](https://github.com/craftcms/commerce/issues/470))
- Added GraphQL support for variants. ([#1315](https://github.com/craftcms/commerce/issues/1315))
- States can now be re-ordered. ([#1284](https://github.com/craftcms/commerce/issues/1284))
- Added the ability to load a cart into the current session. ([#1348](https://github.com/craftcms/commerce/issues/1348))
- Added the ability to pay the outstanding balance on a cart or completed order.
- Added the ability to submit a new `paymentSourceId` at the time of making payment. ([#1283](https://github.com/craftcms/commerce/issues/1283))
- Addresses are automatically populated when selecting a customer on the Edit Order page. ([#1295](https://github.com/craftcms/commerce/issues/1295))
- Added the ability to select an existing customer’s address on the Edit Order page. ([#990](https://github.com/craftcms/commerce/issues/990))
- `commerce/cart/*` JSON responses now include an `availableShippingMethodsOptions` array of shipping method options with prices.
- Added `availableForPurchase` to product GraphQL fields and arguments.
- Added `defaultPrice` to product GraphQL fields and arguments.
- Added `craft\commerce\adjusters\Tax::_getTaxAmount()`.
- Added `craft\commerce\base\TaxEngineInterface`.
- Added `craft\commerce\controllers\AddressesController::actionValidate()`.
- Added `craft\commerce\controllers\AddressesController::getCustomerAddress()`.
- Added `craft\commerce\controllers\AddressesController::getAddressById()`.
- Added `craft\commerce\controllers\CartController::actionLoadCart()`.
- Added `craft\commerce\elements\Order::getAvailableShippingMethodsOptions()`.
- Added `craft\commerce\elements\Variant::getGqlTypeName()`.
- Added `craft\commerce\elements\Variant::gqlScopesByContext()`.
- Added `craft\commerce\elements\Variant::gqlTypeNameByContext()`.
- Added `craft\commerce\engines\TaxEngine`.
- Added `craft\commerce\gql\arguments\elements\Variant`.
- Added `craft\commerce\gql\arguments\interfaces\Variant`.
- Added `craft\commerce\gql\arguments\queries\Variant`.
- Added `craft\commerce\gql\arguments\resolvers\Variant`.
- Added `craft\commerce\gql\arguments\types\elements\Variant`.
- Added `craft\commerce\gql\arguments\types\generators\VariantType`.
- Added `craft\commerce\models\Settings::$loadCartRedirectUrl`.
- Added `craft\commerce\models\ShippingMethodOption`.
- Added `craft\commerce\services\Addresses::removeReadOnlyAttributesFromArray()`.
- Added `craft\commerce\services\Carts::CART_NAME`.
- Added `craft\commerce\services\Customers::getCustomersQuery()`.
- Added `craft\commerce\services\Taxes`.

### Changed
- Improved the line item adding and editing UI on the Edit Order page.
- The amount of “base discount” in a discount action is now spread across the line items from highest priced to lowest.
- A line item‘s `price` and `salePrice` are now rounded before being multiplied by the quantity.
- Improved the consistency of Discount and Tax calculations and rounding system wide.
- Updated the example templates.
- The commerceCurrency twig filter now rounds consistently with currency rounding.

### Fixed
- Fixed an error that could occur when querying product GraphQL using product type. 
- Fixed a bug where it was possible to refund more than the remaining amount of a transaction. ([#1098](https://github.com/craftcms/commerce/issues/1098))
- Fixed a bug where incorrect results could be returned when using the `dateUpdated` order query parameter. ([#1345](https://github.com/craftcms/commerce/issues/1345))
- Fixed a PHP error on the Edit Order page that could occur when viewing an order with a deleted customer. ([#1347](https://github.com/craftcms/commerce/issues/1347))
- Fixed an error that could occur when entering localized numbers on Shipping Rules. ([#1332](https://github.com/craftcms/commerce/issues/1332))
- Fixed a bug that could occur when editing localized decimal prices on Discounts. ([#1174](https://github.com/craftcms/commerce/issues/1174))
- Fixed a bug that could occur while typing an order status message during order recalculation. ([#1309](https://github.com/craftcms/commerce/issues/1309))

### Deprecated
- Deprecated `craft\commerce\services\Carts::$cartName`. `craft\commerce\services\Carts::CART_NAME` should be used instead.
- Deprecated the ability to create percentage based order level discounts.