# Release Notes for Craft Commerce 5.8 (WIP)

### Development

- GraphQL requests authenticated with OAuth access tokens can now query and mutate Commerce data that the authenticated user has access to. ([craftcms/cms#20147](https://github.com/craftcms/cms/pull/20147))
- Users can query their own orders via authenticated GraphQL requests. Users with the “Manage orders” permission can query all orders.
- Added the `productTypes` and `productType` GraphQL queries, and a `productType` field to products’ GraphQL data, enabled via the new “Query for product types” schema component.
- Added the `stores` and `store` GraphQL queries, enabled via the new “Query for stores” schema component.
- Added the `orderStatuses` and `orderStatus` GraphQL queries, enabled via the new “Query for order statuses” schema component.
- Added the `orders`, `order`, and `orderCount` GraphQL queries, enabled via new per-store “Query for orders” schema components.
- Added the `donation` GraphQL query and the `save_Donation` mutation, enabled via new “Donations” schema components.
- Added the `transfers`, `transfer`, and `transferCount` GraphQL queries, and the `save_Transfer` and `deleteTransfer` mutations, enabled via new “Inventory Transfers” schema components. Draft transfers can be marked as pending via `save_Transfer`.
- Added the `save_<productType>_Product`, `save_<productType>_Variant`, `deleteProduct`, and `deleteVariant` GraphQL mutations, enabled via new per-product-type “Edit products” schema components. Product save mutations accept `sortOrder` and `variants` arguments for creating, updating, reordering, and removing the product’s variants, which work like Matrix fields’ `sortOrder` and `entries` mutation arguments.
- Added the `save_Order` and `deleteOrder` GraphQL mutations, enabled via new per-store “Edit orders” schema components. Orders’ line items, addresses, customers, and custom fields can be set, and orders can be marked as complete.

### Extensibility

- Added `craft\commerce\elements\Donation::canSave()`.
- Added `craft\commerce\elements\Donation::GQL_TYPE_NAME`.
- Added `craft\commerce\elements\Order::GQL_TYPE_NAME`.
- Added `craft\commerce\elements\Transfer::GQL_TYPE_NAME`.
- Added `craft\commerce\gql\arguments\elements\Donation`.
- Added `craft\commerce\gql\arguments\elements\Order`.
- Added `craft\commerce\gql\arguments\elements\Transfer`.
- Added `craft\commerce\gql\arguments\mutations\Product`.
- Added `craft\commerce\gql\arguments\mutations\Variant`.
- Added `craft\commerce\gql\interfaces\elements\Donation`.
- Added `craft\commerce\gql\interfaces\elements\Order`.
- Added `craft\commerce\gql\interfaces\elements\Transfer`.
- Added `craft\commerce\gql\mutations\Donation`.
- Added `craft\commerce\gql\mutations\Order`.
- Added `craft\commerce\gql\mutations\Product`.
- Added `craft\commerce\gql\mutations\Transfer`.
- Added `craft\commerce\gql\queries\Donation`.
- Added `craft\commerce\gql\queries\Order`.
- Added `craft\commerce\gql\queries\Settings`.
- Added `craft\commerce\gql\queries\Transfer`.
- Added `craft\commerce\gql\resolvers\elements\Donation`.
- Added `craft\commerce\gql\resolvers\elements\Order`.
- Added `craft\commerce\gql\resolvers\elements\Transfer`.
- Added `craft\commerce\gql\resolvers\mutations\Donation`.
- Added `craft\commerce\gql\resolvers\mutations\Order`.
- Added `craft\commerce\gql\resolvers\mutations\Product`.
- Added `craft\commerce\gql\resolvers\mutations\Transfer`.
- Added `craft\commerce\gql\resolvers\mutations\Variant`.
- Added `craft\commerce\gql\types\elements\Donation`.
- Added `craft\commerce\gql\types\elements\Order`.
- Added `craft\commerce\gql\types\elements\Transfer`.
- Added `craft\commerce\gql\types\generators\DonationType`.
- Added `craft\commerce\gql\types\generators\OrderType`.
- Added `craft\commerce\gql\types\generators\ProductType::generateType()`.
- Added `craft\commerce\gql\types\generators\TransferType`.
- Added `craft\commerce\gql\types\generators\VariantType::generateType()`.
- Added `craft\commerce\gql\types\input\Address`.
- Added `craft\commerce\gql\types\input\LineItem`.
- Added `craft\commerce\gql\types\input\TransferDetail`.
- Added `craft\commerce\gql\types\LineItem`.
- Added `craft\commerce\gql\types\OrderStatus`.
- Added `craft\commerce\gql\types\ProductType`.
- Added `craft\commerce\gql\types\Store`.
- Added `craft\commerce\gql\types\TransferDetail`.
- Added `craft\commerce\helpers\Gql::canQueryOrders()`.

### System

- `productType` is now a reserved product field handle.
- `lineItems`, `orderStatus`, and `store` are now reserved order field handles.
- Craft Commerce now requires Craft CMS 5.12.0 or later.
