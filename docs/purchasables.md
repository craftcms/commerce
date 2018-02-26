# Purchasables

A purchasable is a custom Element Type that can be sold through the cart.

A purchasable:

- Is a custom Element Type (See Craft docs on [Element Types](https://craftcms.com/docs/plugins/working-with-elements))

- Its element type's model inherits from the Base Purchasable Abstract Class located at: `plugins/commerce/Commerce/Base/Purchasable.php`

- Its element type's model meets the `Purchasable` interface. The Interface class is found in `plugins/commerce/Commerce/Interfaces/Purchasables.php`

- Persists itself as an Element with the `craft()->commerce_purchasables->saveElement()` method anywhere you would usually use `craft()->elements->saveElement()`

## Interface

To meet the Purchasable Interface, these core methods need to exist on the element type's model.


### `getDescription()`

This is the description of the purchasable. Would usually be the title, or name of the product.

### `getIsPromotable()`

Whether the purchasable can be subject to discounts or sales.

### `getPrice()`

The price of the purchasable in the primary currency.

### `getPurchasableId()`

The element ID of the purchasable.

### `getSku()`

The stock keeping unit number of the purchasable. Must be unique based on the `commerce_purchasables` table.

### `getSnapshot()`

An array of data that is serialized as json on the line item when the purchasable is added to the cart. This is useful when the purchasable is later deleted, but the cart can still have all relevant data about the purchasable stored within it.

### `getTaxCategoryId()`

The tax category ID of the purchasable. Defaults to the default tax category ID if not supplied.

### `getShippingCategoryId()`

The shipping category ID of the purchasable. Defaults to the default shipping category ID if not supplied.

### `hasFreeShipping()`

Stops the shipping engine from adding shipping costs to a line that contains this purchasable.

## `populateLineItem(Commerce_LineItemModel $lineItem)`

Gives the purchasable the chance to change the saleAmount and price of the line item when it is added to the cart, or the cart recalculates.

## `validateLineItem(Commerce_LineItemModel $lineItem)`

Gived the purchasable the chance to validate the line item model.
Add errors to the lineItem like so:

```php
$lineItem->addError(['options'=>'not valid options submitted for this purchasable']);
```
# Promotions Caveats

## Discount Promotions

Purchasables are able to be discounted by discount promotions as long as the discount conditions do not include conditions around product type or product. Your discount can not currently target a specific purchasable or purchasables, only the order as a whole.

## Sale Promotions

Since sales apply to products before they are added to the cart, purchasables are not currently integrated with the sales promotion engine. We hope to improve this restriction in a future version of Craft Commerce.
