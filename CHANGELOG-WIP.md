# Release Notes for Craft Commerce 5.8 (WIP)

### Development

- Added the `orders`, `order`, and `orderCount` GraphQL queries, enabled via new per-store “Query for orders” schema components.
- Added the `productTypes` and `productType` GraphQL queries, and a `productType` field to products’ GraphQL data, enabled via the new “Query for product types” schema component.
- Added the `stores` and `store` GraphQL queries, enabled via the new “Query for stores” schema component.
- Added the `orderStatuses` and `orderStatus` GraphQL queries, enabled via the new “Query for order statuses” schema component.

### Extensibility

- Added `craft\commerce\elements\Order::GQL_TYPE_NAME`.
- Added `craft\commerce\gql\arguments\elements\Order`.
- Added `craft\commerce\gql\interfaces\elements\Order`.
- Added `craft\commerce\gql\queries\Order`.
- Added `craft\commerce\gql\queries\Settings`.
- Added `craft\commerce\gql\resolvers\elements\Order`.
- Added `craft\commerce\gql\types\elements\Order`.
- Added `craft\commerce\gql\types\generators\OrderType`.
- Added `craft\commerce\gql\types\LineItem`.
- Added `craft\commerce\gql\types\OrderStatus`.
- Added `craft\commerce\gql\types\ProductType`.
- Added `craft\commerce\gql\types\Store`.
- Added `craft\commerce\helpers\Gql::canQueryOrders()`.

### System

- `productType` is now a reserved product field handle.
- `lineItems`, `orderStatus`, and `store` are now reserved order field handles.
