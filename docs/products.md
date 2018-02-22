# Products

Products are the items for sale within your store. These differ from Variants, which track the unique variations of a product.

The product itself is never sold, just a variant of a product. Even a product with no variants has a **default variant** behind it. 

For instance, a t-shirt product would likely have multiple variants, one for each of it's different colors. You would only ever sell those variants, and not the product itself.
A bug that only comes in one color and size might not need variants, but a single "in-built" default variant still exists, which is the item the customer adds to the cart.

Together, Products and Variants describe what is for sale.


# Variants

Variant records track the individual variants of a Product.

Variant records can track some individual properties regarding a variant, such as SKU, price, and dimensions.
These properties are unique to each variant. Additional custom fields can be added to variants to allow other distinguishing traits.

For example, you may be selling a product which is a Baseball Jersey, which comes in the sizes “Small”, “Medium” and “Large”, as well as in the colors of “Red”, “Green” and “Blue”.
For this combination of sizes and colors, you might make a Product Type that has 2 dropdown fields (Color and Size) added to the variant's field layout.
This would enable unique variant data:

- Small, Red
- Small, Green
- Small, Blue
- Medium, Red
- Medium, Green
- Medium, Blue
- Large, Red
- Large, Green
- Large, Blue

This doesn't stop you from using other custom fields to define other special variants, and it also does not ensure each variant has unique combinations of custom fields - that's up to the author.

## Default Variant

Every single product has a default variant. Whenever a product is created, a default variant for that product will be created too.

When a product is able to have multiple variants, the author can choose which one is default. Products that do not have multiple variants still have a default variant, but the author can not add additional variants.

# Purchasables

Anything that can be added to the cart by a customer is called a [Purchasable](en/purchasable.md).

3rd party plugins can provide additional Purchasables, but Craft Commerce only provides a single type of purchasable, the aforementioned 'variant' that belongs to a product.
 