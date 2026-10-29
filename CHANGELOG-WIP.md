# Release Notes for Craft Commerce 5.8 (WIP)

### Development

- Added the `productTypes` and `productType` GraphQL queries, and a `productType` field to products’ GraphQL data, enabled via the new “Query for product types” schema component.
- Added the `stores` and `store` GraphQL queries, enabled via the new “Query for stores” schema component.
- Added the `orderStatuses` and `orderStatus` GraphQL queries, enabled via the new “Query for order statuses” schema component.

### Extensibility

- Added `craft\commerce\gql\queries\Settings`.
- Added `craft\commerce\gql\types\OrderStatus`.
- Added `craft\commerce\gql\types\ProductType`.
- Added `craft\commerce\gql\types\Store`.

### System

- `productType` is now a reserved product field handle.
