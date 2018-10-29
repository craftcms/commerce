# Line Item Model

Line items belong to an order and contain information about the purchasable added to the cart. They also contain information on the quantity of that purchasable as well as totals, tax, shipping, and promotion information that get used to calculate the line item total.

### id

The ID of the line item.

### price

The standard price of the purchasable.

### saleAmount

The amount of sales discount that should be applied to the price. Usually a negative number. To determine how this number was calculated, see the `salesApplied` attribute.

### salePrice

The price after [saleAmount](#saleamount) was applied.

### qty

How many of this purchasable are being purchased in this line item.

### subtotal

salePrice * qty

### tax

Tax applied to the line item

### shippingCost

Shipping costs applied to the line item

### discount

Discount applied to the line item

### total

Sum of `subtotal + tax + shippingCost + discount`.

### length

The length of the variant on this line item

### weight

The weight of the variant on this line item

### height

The height of the variant on this line item

### width

The width of the variant on this line item

### note

The note added to the line item when the purchasable was added to the cart, or updated.

### snapshot

The snapshot is JSON-encoded information about the purchasable at the time of when the line item was last updated.
This means if the purchasable (variant) is deleted you can still get to core information about what was purchased.

### order

The OrderModel this line item belongs to.

### purchasable

The purchasable Model that this line item represents. This will likely be a VariantModel in most cases, unless you have installed additional purchasables.

### taxCategory

The tax category the purchasable belongs to.

### onSale

Whether the line item’s `price` is different than the `salePrice`.

### salesApplied

An array of `Commerce_SaleModels` that were used to calculate the `salePrice` of this variant. You could also check the length of this array to determine if the variant is on sale. e.g `{% if variant.salesApplied|length %}This variant is on sale{% endif %}
