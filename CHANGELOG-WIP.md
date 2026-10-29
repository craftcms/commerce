# Release Notes for Craft Commerce 5.8 (WIP)

### Development

- GraphQL requests authenticated with OAuth access tokens can now query products, variants, orders, stores, product types, and order statuses that the authenticated user has access to. ([craftcms/cms#20147](https://github.com/craftcms/cms/pull/20147))
- Users can query their own orders via authenticated GraphQL requests. Users with the “Manage orders” permission can query all orders.
- Added the `save_<productType>_Product`, `save_<productType>_Variant`, `deleteProduct`, and `deleteVariant` GraphQL mutations, enabled via new per-product-type “Edit products” schema components. Product save mutations accept a `variants` argument for creating, updating, and removing the product’s variants.
- Added the `orders`, `order`, and `orderCount` GraphQL queries, enabled via new per-store “Query for orders” schema components.
- Added the `productTypes` and `productType` GraphQL queries, and a `productType` field to products’ GraphQL data, enabled via the new “Query for product types” schema component.
- Added the `stores` and `store` GraphQL queries, enabled via the new “Query for stores” schema component.
- Added the `orderStatuses` and `orderStatus` GraphQL queries, enabled via the new “Query for order statuses” schema component.

### Extensibility

- Added `craft\commerce\elements\Order::GQL_TYPE_NAME`.
- Added `craft\commerce\gql\arguments\elements\Order`.
- Added `craft\commerce\gql\arguments\mutations\Product`.
- Added `craft\commerce\gql\arguments\mutations\Variant`.
- Added `craft\commerce\gql\interfaces\elements\Order`.
- Added `craft\commerce\gql\mutations\Product`.
- Added `craft\commerce\gql\queries\Order`.
- Added `craft\commerce\gql\queries\Settings`.
- Added `craft\commerce\gql\resolvers\elements\Order`.
- Added `craft\commerce\gql\resolvers\mutations\Product`.
- Added `craft\commerce\gql\resolvers\mutations\Variant`.
- Added `craft\commerce\gql\types\elements\Order`.
- Added `craft\commerce\gql\types\generators\OrderType`.
- Added `craft\commerce\gql\types\generators\ProductType::generateType()`.
- Added `craft\commerce\gql\types\generators\VariantType::generateType()`.
- Added `craft\commerce\gql\types\LineItem`.
- Added `craft\commerce\gql\types\OrderStatus`.
- Added `craft\commerce\gql\types\ProductType`.
- Added `craft\commerce\gql\types\Store`.
- Added `craft\commerce\helpers\Gql::canQueryOrders()`.

### System

- `productType` is now a reserved product field handle.
- `lineItems`, `orderStatus`, and `store` are now reserved order field handles.
- Craft Commerce now requires Craft CMS 5.12.0 or later.
