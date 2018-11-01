# Sales

Setting up sales allows a store owner to set up promotional discounts on products that apply dynamically based on conditions.

A sale differs from a discount, in that it is applied *before* the item is added to the cart, whereas a discount applies only when the item is in the cart or a coupon code is applied to the cart.

You would typically list the price of the item in the store by its sale price. If no sales apply to the current product, the sale price would be equal to the regular price.

Sales are ordered in the Control Panel, and the system always runs through each sale in order when determining the `salePrice` of the purchasable.

## Conditions

When creating a sale, you have a number of conditions that are checked to determine whether the sale should be applied to the purchasable. All conditions must match to have the sale applied. Leaving the condition blank ignores that condition.

### Start date

When the sale can start to be applied to matching products.

### End date

When the sale stops being applied to matching products.

### User Group

Whether the cart’s customer belongs to one of the matching user groups.

### Variant

Whether the purchasable being matched is one of the selected variants.

### Category

Whether the purchasable being matched is related to the category selected in this field.

For example, you might have a category of products in the “Womens Sport” department category, and this allows you to put all products in that category on sale.

In the case of the only built-in purchasable, variants, the category can be related to either the product or the variant to match this condition.

Each custom purchasable can decide to determine how they consider the category being related.

### Other Purchasables

Any other custom purchasable a third party system adds to Commerce can show up here as a condition.

## Actions

If the conditions match the current context (variant, user, cart date etc) then the actions are applied to the purchasable.

You have 4 different ways to apply a price effect:

### Reduce the price by a percentage of the original price

Enter a % amount to take of the price of the purchasable.

For example the purchasable might be $100, if you set this value to 80%, the sale price of the purchasable will be $20.

### Set the price to a percentage of the original price

Enter a % amount to make the price of the purchasable.

For example the purchasable might be $100, if you set this value to 20%, the sale price of the purchasable will be $20.

### Reduce the price by a flat amount

Enter an flat currency amount to take off the purchasables price.

### Set the price to a flat amount

Enter an flat currency amount to determine the salePrice of the purchasable.

There are also additional options for how this sale affect is applied to the product:

### Ignore previous matching sales if this sale matches. (Checkbox)

This setting will disregard any previous sale that affected the price of the item matched in this sale.

For example `Sale 1` reduced the price by 10%, checking this box within `Sale 2` will apply its affect on the original price of the purchasable, ignoring the 10% off.

This is automatically true if either of the following pricing effects are used:

- Set the price to a percentage of the original price
- Set the price to a flat amount

This setting is related to the purchasable being affected.

### Do not apply subsequent matching sales beyond applying this sale. (Checkbox)

After this sale matches the order, do not apply any other sales, based on the order of sales in the Control Panel.

This is a sale level option, not a purchasable level option like the above `Ignore previous matching sales if this sale matches.`
