# Add to Cart

To add something to the cart you need a [Purchasable](purchasables.md) model, and it's `purchasableId`. You then submit a `purchasableId` to  the `commerce/cart/updateCart` form action to add it to the cart.

The core [Variant Model](variant-model.md) are [Purchasable](purchasables.md) and have a `purchasableId`. Products are not purchasable on their own, all products have at one default variant. See the core concept doc on variants for more information. 

The following is an example of getting the first product found in your store. We then get the product's default variant and use its purchasableId in the form that will add that item to the cart:

```twig
{% set product = craft.products.one() %}
{% set variant = product.defaultVariant %}

<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    <input type="hidden" name="redirect" value="commerce/cart">
    <input type="hidden" name="qty" value="1">
    <input type="hidden" name="purchasableId" value="{{ variant.purchasableId }}">
    <input type="submit" value="Add to cart">
</form>
```
* The `qty` param is not required as it defaults to `1` if not supplied.

The above is a simple example, if your product's type has multiple variants you could loop over all the products variants and allow the customer to choose the variant from a dropdown:

```twig
{% set product = craft.products.one() %}

<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    <input type="hidden" name="redirect" value="commerce/cart">
    <input type="hidden" name="qty" value="1">
    <select name="purchasableId">
        {% for variant in product.variants %}
            <option value="{{ variant.purchasableId }}">{{ variant.sku }}</option>
        {% endfor %}
    </select>
    <input type="submit" value="Add to cart">
</form>
```
>{Warning} When using the `commerce/cart/updateCart` form action, the redirect is only followed if *all* updates submitted succeed.

# Line item options and notes

When submitting a product to the cart, you can optionally include a text note from the customer, or arbitrary data in an options param.

Here is an example of an add to cart form with both a `notes` and `options` param.

```twig
{% set product = craft.products.one() %}
{% set variant = product.defaultVariant %}
<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    <input type="hidden" name="redirect" value="commerce/cart">
    <input type="hidden" name="qty" value="1">

    <input type="text" name="note" value="">

    <select name="options[engraving]">
        <option value="happy-birthday">Happy Birthday</option>
        <option value="good-riddance">Good Riddance</option>
    </select>

    <select name="options[giftwrap]">
        <option value="yes">Yes Please</option>
        <option value="no">No Thanks</option>
    </select>

    <input type="hidden" name="purchasableId" value="{{ variant.purchasableId }}">
    <input type="submit" value="Add to cart">
</form>
```

In the above example we:

- Allowed a customer to input a `note` with a text field.
- Allowed a customer to choose an option called 'engraving' with 2 prepared values.
- Allowed a customer to choose an option called 'giftwrap' with 2 prepared values.

>{Warning} The options and notes param data is not validated. A user could submit any data. If you need to validate the options, use the [beforeAddToCart](https://craftcommerce.com/docs/events-reference#commerce_cart.onbeforeaddtocart) event.

Once the order is complete, the notes and options can be found in the View Order screen.

<img src="assets/lineitem-options-review.png" width="509" alt="Line Item Option Review.">

# Options uniqueness

The options data submitted to the line item are hashed into an `optionsSignature` for uniqueness. If you submit the same purchasableId to the cart with different option data, two line items with be created.

Another way to think about it is that each line item is unique based on the combination of `purchasableId` and `optionsSignature`.

# Adding multiple purchasables to the cart

You can add multiple purchasables to the cart in an update cart form. You supply the data to the controller in a different format. All purchasables IDs are supplied in a `purchasables` form array like so:

```twig
{% set product = craft.products.one() %}
<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    <input type="hidden" name="redirect" value="commerce/cart">

    {% for variant in product.variants %}
        <input type="hidden" name="purchasables[{{loop.index}}]][id]" value="{{ variant.purchasableId }}">
        <input type="hidden" name="purchasables[{{loop.index}}]][qty]" value="1">
        <input type="hidden" name="purchasables[{{loop.index}}]][note]" value="1">
    {% endfor %}

    <input type="submit" value="Add all variants to cart">
</form>
```

While using multi-add the same rules apply for updating a quantity vs adding to cart, based on the uniquessness of the options signature and purchasableId.
