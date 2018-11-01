# Purchasables

A purchasable is a custom Craft Element Type that can be sold through the cart.

A purchasable:

- is an [element types](https://docs.craftcms.com/v3/extend/element-types.html)
- implements `craft\commerce\base\PurchasableInterface`
- can extend `craft\commerce\base\Purchasable`

## Implementation

To implement the Purchasable Interface, inherit from the base Purchasable, and also implement these methods:

### `getId()`

The ID of the element.

### `getDescription()`

This is the description of the purchasable. Would usually be the title, or name of the product. This is used for display in the order, even if the purchasable is later deleted.

### `getPrice()`

The default price of the item.

### `getSku()`

The stock keeping unit number of the purchasable. Must be unique based on the `commerce_purchasables` table.

When you inherit from `craft\commerce\base\Purchasable` a unique validation rule for the `sku` attribute is added to the `rules()` method.

### `getSnapshot()`

An array of data that is serialized as JSON on the line item when the purchasable is added to the cart. This is useful when the purchasable is later deleted, but the cart can still have all relevant data about the purchasable stored within it.

### `getTaxCategoryId()`

The tax category ID of the purchasable.

Defaults to the default tax category ID.

### `getShippingCategoryId()`

The shipping category ID of the purchasable.

Defaults to the default shipping category ID.

### `hasFreeShipping()`

Stops the shipping engine from adding shipping costs adjustment to a line item containing this purchasable.

### `getIsPromotable()`

Whether the purchasable can be subject to discounts or sales.

### `getIsAvailable()`

Whether the purchasable can be added to a cart.

Should return `true` or `false` if the purchasable can be added to, or exist on, the cart.

Base Purchasable defaults to `true` always.

## `populateLineItem(Commerce_LineItemModel $lineItem)`

Gives the purchasable the chance to change the `saleAmount` and `price` of the line item when it is added to the cart, or when the cart recalculates.

## `afterOrderComplete(Order $order, LineItem $lineItem)`

Runs any logic needed for this purchasable after it was on an order that was just completed (not when an order was paid, although paying an order will complete it).

This is called for each line item the purchasable was contained within.

For example, variants use this method to deduct stock.

## `getPromotionRelationSource()`

Returns the source param value for a element relation query, for use with promotions. For example, a sale promotion on a category need to know if the purchasable is related.

Defaults to the ID of the purchasable element, which would be sufficient for most purchasables.
