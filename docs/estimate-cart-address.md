# Estimate Cart Addresses

You may wish to allow a customer to estimate their shipping or tax costs before they are asked to enter their full address details.

The cart accepts the setting of an estimated shipping address and an estimated billing address. These addresses are used in the calculations if the cart does not contain a billing or shipping address.

## Adding a shipping estimate address to the cart

Adding or updating the estimated addresses on the order is done using the `commerce/cart/update-cart` form action.

You are able to test the existence of estimate addresses by checking the `estimatedShippingAddressId` and `estimatedBillingAddressId` attributes on the [cart](api:craft\commerce\elements\Order) object.

The example below shows how you can add a shipping estimate address to the cart.

```twig
{% set cart = craft.commerce.carts.cart %}

<form method="POST">
    <input type="hidden" name="action" value="commerce/cart/update-cart">
    <input type="hidden" name="estimatedBillingAddressSameAsShipping" value="1">

    {% if not cart.estimatedShippingAddressId %}
        <select name="estimatedShippingAddress[countryId]">
            {% for key, option in craft.commerce.countries.allCountriesAsList %}
                <option value="{{ key }}">{{ option }}</option>
            {% endfor %}
        </select>

        <select name="estimatedShippingAddress[stateValue]">
            {% for states in craft.commerce.states.allStatesAsList %}
                {% for key, option in states %}
                    <option value="{{ key }}">{{ option }}</option>
                {% endfor %}
            {% endfor %}
        </select>

        <input type="text" name="estimatedShippingAddress[zipCode]" value="">
    {% endif %}


    {% if cart.availableShippingMethods|length and cart.estimatedShippingAddressId %}
        {% for handle, method in cart.availableShippingMethods %}
            <label>
                <input type="radio" name="shippingMethodHandle" value="{{ handle }}" {% if handle == cart.shippingMethodHandle %}checked{% endif %} />
                {{ method.name }} - {{ method.priceForOrder(cart)|commerceCurrency(cart.currency) }}
            </label>
        {% endfor %}
    {% endif %}

    <input type="submit" value="Submit">
</form>
```

<api:craft\commerce\adjusters\Tax> and <api:craft\commerce\adjusters\Shipping> adjusters that are based on estimated address data contain an `isEstimated` attribute.

A full example of this can be seen in the [example templates](example-templates.md) on the cart page.
