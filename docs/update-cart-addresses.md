You may wish for the customer to supply a shipping and billing address for the order. Although not required, the shipping address enables the tax and shipping engine to more accurately supply shipping options and and tax costs

You can see if the cart has a billing and shipping address has been set with:

```
{% if cart.shippingAddress %}
	{{ cart.shippingAddress.firstName }} ...etc
{% endif %}
```
and
```
{% if cart.billingAddress %}
  {{ cart.billingAddress.firstName }} ... etc
{% endif %}
```

Both cart attributes return an [Address Model](address-model.md), or `null` if no addresses are set.


## Adding or updating the shipping and billing address selected for the current cart.

Adding or updating the addresses on the order is done using the `commerce/cart/updateCart` form action.

There are a number of ways you can do this:

### 1. Using an existing Address ID

The first example below shows adding the first address owned by the customer as the `shippingAddressId` and also setting it as the `billingAddressId` by using the param `sameAddress`:

```
{% set address = craft.commerce.customer.addresses|first %}

<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/updateCart">
    <input type="hidden" name="redirect" value="commerce/cart">
    <input type="hidden" name="shippingAddressId" value="{{ address.id }}">
    <input type="hidden" name="sameAddress" value="1">
    <input type="submit" value="Submit">
</form>
```

Another way of achieving the same thing is is setting both addresses explicitly:

```
{% set address = craft.commerce.customer.addresses|first %}

<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/updateCart">
    <input type="hidden" name="redirect" value="commerce/cart">
    <input type="hidden" name="shippingAddressId" value="{{ address.id }}">
    <input type="hidden" name="billingAddressId" value="{{ address.id }}">
    <input type="submit" value="Submit">
</form>
```

### 2. Submit New Addresses during checkout

Another alternative, if the user wanted to submit new addresses, (they may be a guest) is submitting the address form data directly during checkout.

This will only work if the shippingAddressId is not supplied or is set to a non ID like the word 'new'.
If `shippingAddressId` is an integer then the address form data is ignored and the form action attempts to set the shipping address to that of the ID.

```
<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/updateCart">
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

## Summary

As you can see from both examples, the shipping address is *always* submitted, and the billingAddress can either be also included, or set to the same address as the shipping address with the `sameAddress` param.

