# Update Cart Addresses

You may wish for the customer to supply a shipping and billing address for the order. Although not required, the shipping address enables the tax and shipping engine to more accurately supply shipping options and and tax costs

You can see if the cart has a billing and shipping address has been set with:

```twig
{% if cart.shippingAddress %}
    {{ cart.shippingAddress.firstName }} ...etc
{% endif %}
```
and
```twig
{% if cart.billingAddress %}
  {{ cart.billingAddress.firstName }} ... etc
{% endif %}
```

Both cart attributes return an <api:craft\commerce\models\Address> object, or `null` if no addresses are set.

## Adding or updating the shipping and billing address selected for the current cart.

Adding or updating the addresses on the order is done using the `commerce/cart/update-cart` form action.

There are a number of ways you can do this:

### 1. Using an existing address ID as the default

The example below shows how you can add the first address owned by the customer as the `shippingAddressId` while also setting it as the order’s billing address, by using the `billingAddressSameAsShipping` param:

```twig
{% set address = craft.commerce.customer.addresses|first %}

<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    <input type="hidden" name="redirect" value="commerce/cart">
    <input type="hidden" name="shippingAddressId" value="{{ address.id }}">
    <input type="hidden" name="billingAddressSameAsShipping" value="1">
    <input type="submit" value="Submit">
</form>
```

The inverse is possible as well, providing the `billingAddressId` and setting the `shippingAddressSameAsBilling` param.

Another way of achieving the same thing is is setting both addresses explicitly:

```twig
{% set address = craft.commerce.customer.addresses|first %}

<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    <input type="hidden" name="redirect" value="commerce/cart">
    <input type="hidden" name="shippingAddressId" value="{{ address.id }}">
    <input type="hidden" name="billingAddressId" value="{{ address.id }}">
    <input type="submit" value="Submit">
</form>
```

### 2. Submit new addresses during checkout

Another alternative, if the user wanted to submit new addresses, (they may be a guest) is submitting the address form data directly during checkout.

This will only work if the `shippingAddressId` is not supplied or is set to a non-ID like the word `new`.
If `shippingAddressId` is an integer then the address form data is ignored and the form action attempts to set the shipping address to that of the ID.

```twig
<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    <input type="hidden" name="redirect" value="commerce/cart">
    <input type="hidden" name="shippingAddressId" value="new">
    <input type="text" name="shippingAddress[firstName]" value="">
    <input type="text" name="shippingAddress[lastName]" value="">
    <select name="shippingAddress[countryId]">
    {% for id, name in craft.commerce.countriesList %}
      <option value="{{ id }}">{{ name }}</option>
    {% endfor %}
        </select>
      <input type="hidden" name="sameAddress" value="1">
    <input type="submit" value="Add to cart">
</form>
```

### 3. Select from existing addresses

If your customers have added multiple addresses, you can use radio buttons to select the proper `shippingAddressId` and `billingAddressId`, or create a new address on-the-fly:

```twig
{% set cart = craft.commerce.carts.cart %}

<form class="form" method="post">
    {{ csrfInput() }}
    <input type="hidden" name="action" value="commerce/cart/update-cart">

    {% set customerAddresses = craft.commerce.customer.addresses %}

    {# Check if we have saved addresses: #}
    {% if customerAddresses | length %}
        <div class="shipping-address">
            {% for address in customerAddresses %}
                <label>
                    <input type="radio" name="shippingAddressId" value="{{ address.id }}" {{- cart.shippingAddressId ? ' checked' : null }}>
                    {# Identifying address information, up to you! #}
                </label>
            {% endfor %}
        </div>

        <div class="billing-address">
            {% for address in customerAddresses %}
                <label>
                    <input type="radio" name="billingAddressId" value="{{ address.id }}" {{- cart.billingAddressId ? ' checked' : null }}>
                    {# Identifying address information, up to you! #}
                </label>
            {% endfor %}
        </div>
    {% else %}
        {# If no existing addresses were found, provide forms to add new ones: #}
        <div class="new-billing-address">
            <label>
                First Name
                <input type="text" name="billingAddress[firstName]">
            </label>
            {# ...remainder of address fields... #}
        </div>

        <div class="new-shipping-address">
            <label>
                First Name
                <input type="text" name="shippingAddress[firstName]">
            </label>
            {# ...remainder of address fields... #}
        </div>
    {% endif %}
</form>
```

You may need to create other custom routes to allow customers to manage these addresses, or introduce some logic in the browser to hide and show new address forms based on the type(s) of addresses you need.

## Summary

When using the `update-cart` action, you may include both new shipping and billing address (properly nested under their respective keys, `shippingAddress[...]` and `billingAddress[...]`), or select existing addresses using one or the other of the `shippingAddressId` and `billingAddressId` params. In either example, you can include `shippingAddressSameAsBilling` or `billingAddressSameAsShipping` to synchronize the attached addresses.
