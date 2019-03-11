# Purchasables

A purchasable is a custom Craft Element Type that can be sold through the cart.

A purchasable:

- is an [element types](https://docs.craftcms.com/v3/extend/element-types.html)
- implements `craft\commerce\base\PurchasableInterface`
- should extend `craft\commerce\base\Purchasable`

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
This validator ignored soft-deleted purchasables in it’s validator. Uniqueness if only checked for non-trashed purchasables. 

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

### `populateLineItem(Commerce_LineItemModel $lineItem)`

Gives the purchasable the chance to change the `saleAmount` and `price` of the line item when it is added to the cart, or when the cart recalculates.

### `afterOrderComplete(Order $order, LineItem $lineItem)`

Runs any logic needed for this purchasable after it was on an order that was just completed (not when an order was paid, although paying an order will complete it).

This is called for each line item the purchasable was contained within.

For example, variants use this method to deduct stock.

### `getPromotionRelationSource()`

Returns the source param value for a element relation query, for use with promotions. For example, a sale promotion on a category need to know if the purchasable is related.

Defaults to the ID of the purchasable element, which would be sufficient for most purchasables.


## Purchasable deletion

Soft-deletion was added in Craft 3.1 and all elements get soft-deleted automatically without needing to do anything.

When you inherit from `craft\commerce\base\Purchasable` and your element is saved, we automatically update the `commerce_purchasables` table with the 
purchasable’s `sku` so that all purchasables have a central location to check their `sku` uniqueness.

The uniqueness of your `sku` is automatically validated for you when extending `craft\commerce\base\Purchasable`.

We take care of only validating non-trashed purchasables. This means that trashed purchasables will still be in this table until garbage collection is run.

## Restoring soft-deleted purchasables

If you decide to support restoring of your purchasables element, you need to make sure your restored purchasable’s `sku` is unique.

You would do this in the following way:

 1) Override the `beforeRestore()` method in your purchasable element
 2) Within that method, check to see if any non-trashed purchasables has the same `sku` as the purchasable being restored. You could do that like this:

```php

if (!parent::beforeDelete()) {
    return false;
}
        
$found = (new Query())->select(['[[p.sku]]', '[[e.id]]'])
    ->from('{{%commerce_purchasables}} p')
    ->leftJoin(Table::ELEMENTS . ' e', '[[p.id]]=[[e.id]]')
    ->where(['[[e.dateDeleted]]' => null, '[[p.sku]]' => $this->getSku()])
    ->andWhere(['not', ['[[e.id]]' => $this->getId()]])
    ->count();
```

3) If there is live (non-trashed) purchasable with the same `sku`, make your purchasable’s `sku` unique and update the relevant tables. For example:

```php
if ($found) {
    
    $this->sku = $this->getSku() . '-1'; // make unique

    // Update variant table with new SKU
    Craft::$app->getDb()->createCommand()->update('{{%commerce_variants}}',
        ['sku' => $this->sku],
        ['id' => $this->getId()]
    )->execute();

    if ($this->isDefault) {
        Craft::$app->getDb()->createCommand()->update('{{%commerce_products}}',
            ['defaultSku' => $this->sku],
            ['id' => $this->productId]
        )->execute();
    }

    // Update purchasable table with new SKU
    Craft::$app->getDb()->createCommand()->update('{{%commerce_purchasables}}',
        ['sku' => $this->sku],
        ['id' => $this->getId()]
    )->execute();
}
```

It's important to update both your own tables as well as the `commerce_purchasables` table.
