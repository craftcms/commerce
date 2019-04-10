# Add to Cart

To add something to the cart, submit the ID of a [purchasable](purchasables.md) element as a `purchasableId` param to the `commerce/cart/update-cart` action.

::: tip
Products are not purchasable on their own; all products have at least one default variant. See [Products](products.md) for more information.
:::

The following is an example of getting the first product found in your store. We then get the product’s default variant and use its ID in the form that will add that item to the cart:

```twig
{% set product = craft.products.one() %}
{% set variant = product.defaultVariant %}

<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    {{ redirectInput('shop/cart') }}
    <input type="hidden" name="qty" value="1">
    <input type="hidden" name="purchasableId" value="{{ variant.id }}">
    <input type="submit" value="Add to cart">
</form>
```
* The `qty` param is not required as it defaults to `1` if not supplied.

The above is a simple example, if your product’s type has multiple variants you could loop over all the products variants and allow the customer to choose the variant from a dropdown:

```twig
{% set product = craft.products.one() %}

<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    {{ redirectInput('shop/cart') }}
    {{ csrfInput() }}
    <input type="hidden" name="qty" value="1">
    <select name="purchasableId">
        {% for variant in product.variants %}
            <option value="{{ variant.id }}">{{ variant.sku }}</option>
        {% endfor %}
    </select>
    <input type="submit" value="Add to cart">
</form>
```

::: warning
In the Lite edition of Craft Commerce only single line item can exist in the cart. Whenever a customer adds something to the cart, it replaces whatever item was in the cart.
If multiple items are added to the cart in a single request, only the last item submitted is added to the cart.    
:::

::: warning
When using the `commerce/cart/update-cart` form action, the redirect is only followed if *all* updates submitted succeed.
:::

## Line item options and notes

When submitting a product to the cart, you can optionally include a text note from the customer, or arbitrary data in an options param.

Here is an example of an add to cart form with both a `notes` and `options` param.

```twig
{% set product = craft.products.one() %}
{% set variant = product.defaultVariant %}
<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    {{ redirectInput('shop/cart') }}
    {{ csrfInput() }}
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

    <input type="hidden" name="purchasableId" value="{{ variant.id }}">
    <input type="submit" value="Add to cart">
</form>
```

In the above example we:

- Allowed a customer to input a `note` with a text field.
- Allowed a customer to choose an option called `engraving` with 2 prepared values.
- Allowed a customer to choose an option called `giftwrap` with 2 prepared values.

::: warning
The options and notes param data is not validated. A user could submit any data. Use front-end validation.
:::

Once the order is complete, the notes and options can be found on the View Order page.

<img src="./assets/lineitem-options-review.png" width="509" alt="Line Item Option Review.">

## Options uniqueness

The options data submitted to the line item are hashed into an `optionsSignature` for uniqueness. If you submit the same purchasable ID to the cart with different option data, two line items will be created.

Another way to think about it is that each line item is unique based on the combination of `purchasableId` and `optionsSignature`.

## Adding multiple purchasables to the cart

You can add multiple purchasables to the cart in an update cart form. You supply the data to the controller in a different format. All purchasables IDs are supplied in a `purchasables` form array like so:

```twig
{% set product = craft.products.one() %}
<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    {{ redirectInput('shop/cart') }}
    {{ csrfInput() }}

    {% for variant in product.variants %}
        <input type="hidden" name="purchasables[{{loop.index}}][id]" value="{{ variant.id }}">
        <input type="hidden" name="purchasables[{{loop.index}}][qty]" value="1">
        <input type="hidden" name="purchasables[{{loop.index}}][note]" value="1">
    {% endfor %}

    <input type="submit" value="Add all variants to cart">
</form>
```

While using multi-add the same rules apply for updating a quantity vs adding to cart, based on the uniquessness of the options `signature` and `purchasableId`.

As shown in the example above,  a unique index key is required to group the purchasable ID to its related `notes` and `options` and `qty` param. Using `{{loop.index}}` is an easy way to do this.

## Updating line items

Once the purchasable has been added to the cart, your customer may want to update the `qty` or `note`, they can do this by updating a line item.

Line items can have their `qty`, `note`, and `options`updated. They can also be removed.

To update a line item, submit a form array param with the name of `lineItems`, with the ID of the array key being the line item ID.

Example:

```twig
<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    {{ redirectInput('shop/cart') }}
    {{ csrfInput() }}
    <input type="text" placeholder="My Note" name="lineItems[LINE_ITEM_ID][note]" value="{{ item.note }}">
    <input type="number" name="lineItems[LINE_ITEM_ID][qty]" min="1" value="{{ item.qty }}">
    <input type="submit" value="Update Line Item">
</form>
```

In the example above we are allowing for the editing of one line item. You would replace `LINE_ITEM_ID` with the ID of the line item you wanted to edit. Usually you would just loop over all line items and insert `{{ item.id }}` there, allowing your customers to update multiple line items at once.

To remove a line item, simply send a `lineItems[LINE_ITEM_ID][remove]` param in the request. You could do this by adding a checkbox to the form above that looks like this:

```twig
<input type="checkbox" name="lineItems[LINE_ITEM_ID][remove]" value="1"> Remove item<br>
```

The example templates contain all of the above examples of adding and updating the cart within a full checkout flow.