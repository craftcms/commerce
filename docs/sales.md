# Sales

Setting up sales allows a store owner to set up promotional discounts on products that apply dynamically based on conditions.

A sale differs from a discount, in that it is applied *before* the item is added to the cart, whereas a discount applies only when the item is in the cart or a coupon code is applied to the cart.

You would typically list the price of the item in the store by its sale price. If no sales apply to the current product, the sale price would be equal to the regular price.


# Conditions

When creating a sale, you have a number of conditions that are checked to determine whether the sale should be applied to the product. All conditions must match to have the sale applied. Leaving the condition blank ignores that condition.

## Start date

When the sale can start to be applied to matching products.

## End date

When the sale stops being applied to matching products.

## User Group

Whether the cart's customer belongs to one of the matching user groups.

## Product Type

Whether the product is of this product type.

## Product

Whether the product being matched is one of the selected products.

# Actions

If the conditions match the current context (cart, product, user) then the actions are applied to the order. You have 2 options for an action on the matching products:

## Flat amount off

Take a flat amount of currency off the price of the item. If the matching sales take more than the value of the product, the product becomes free.

## Percentage amount off

Take a percentage amount of currency off the price of the item. This is based on the original price, even if other sales are applied. If the matching sales take more than the value of the product, the product becomes free.
