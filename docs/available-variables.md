# Available Variables

The following are common methods you will want to call in your front end templates:

## craft.commerce.settings

To get the Craft Commerce general settings model:

```twig
{% set settings = craft.commerce.settings %}
```

## craft.products

See [craft.products](craft-products.md)

## craft.orders

See [craft.orders](craft-orders.md)

## craft.commerce.carts.cart

See [craft.commerce.carts.cart](craft-commerce-carts-cart.md)

## craft.commerce.countries.allCountries

Returns an array of [Country Models](country-model.md).

```twig
<select>
{% for country in craft.commerce.countries %}
	<option value="{{ country.id }}">{{ country.name }}</option>
{% endfor %}
</select>
```

## craft.commerce.countries.countriesAsList

Returns a list usable for a dropdown select box.

Data returned as `[32:'Australia', 72:'USA']`

```twig
<select>
{% for id, countryName in craft.commerce.countriesList %}
	<option value="{{ id }}">{{ countryName }}</option>
{% endfor %}
</select>
```

## craft.commerce.states

Returns an array of [State Modles](state-model.md).

```twig
<select>
{% for states in craft.commerce.countries %}
	<option value="{{ state.id }}">{{ state.name }}</option>
{% endfor %}
</select>
```

## craft.commerce.states.allStatesAsList

Returns an array indexed by country IDs, usable for a dropdown select box.

Data returned as `[72:[3:'California', 4:'Washington'],32:[7:'New South Wales']]`

```twig
<select>
{% for countryId, states in craft.commerce.states.allStatesAsList %}
	<optgroup label="{{ craft.commerce.countries.countriesAsList[countryId] }}">
	{% for stateId, stateName in craft.commerce.states.allStatesAsList[countryId] %}
		<option value="{{ stateId }}">{{ stateName }}</option>
	{% endfor %}
  </optgroup>
{% endfor %}
</select>
```

## cart.availableShippingMethods

Returns the shipping methods available to applied to the current cart. Will not include some shipping methods if none of the shipping method's rules can match the cart.

```twig
{% for handle, method in cart.availableShippingMethods %}
    <label>
        <input type="radio" name="shippingMethod" value="{{ handle }}"
               {% if handle == cart.shippingMethodHandle %}checked{% endif %} />
        <strong>{{ method.name }}</strong> {{ method.amount|currency(cart.currency) }}
    </label>
{% endfor %}
```

## craft.commerce.gateways.allFrontEndGateways

Returns all payment gateway available to the customer.

```twig
{% if not craft.commerce.gateways.allFrontEndGateways|length %}
    <p>No payment methods available.</p>
{% endif %}

{% if craft.commerce.gateways.allFrontEndGateways|length %}
<form method="POST" id="paymentMethod" class="form-inline">

    <input type="hidden" name="action" value="commerce/cart/updateCart">
    <input type="hidden" name="redirect" value="commerce/checkout/payment">
    {{ getCsrfInput() }}

    <label for="">Payment Method</label>
    <select id="gatewayId" name="gatewayId" class="form-control" >
        {% for id,name in craft.commerce.gateways.allFrontEndGateways %}
            <option value="{{ id }}" {% if id == cart.paymentMethod.id %}selected{% endif %}>{{ name }}</option>
        {% endfor %}
    </select>

</form>
{% endif %}
```

## craft.commerce.orderStatuses.allOrderStatuses

Returns an array of all custom order statuses [Order Status Model](order-status-model.md) set up in the system.

```twig
{% set statuses = craft.commerce.orderStatuses.allOrderStatuses %}
```

## craft.commerce.taxCategories.allTaxCategories

Returns an array of all tax categories set up in the system.

```twig
{% for taxCategory in craft.commerce.taxCategories.allTaxCategories %}
{{ taxCategory.id }} - {{ taxCategory.name }}
{% endfor %}
```

## craft.commerce.productTypes.allProductTypes

Returns an array of all product types set up in the system.

```twig
{% for type in craft.commerce.productTypes.allProductTypes %}
{{ type.handle }} - {{ type.name }}
{% endfor %}
```

## craft.commerce.orderStatuses.allOrderStatuses

Returns an array of all order statuses [Order Status Model](order-status-model.md) set up in the system.

```twig
{% for status in craft.commerce.orderStatuses.allOrderStatuses %}
{{ status.handle }} - {{ status.name }}
{% endfor %}
```

## craft.commerce.discounts.allDiscounts

Returns an array of all discounts set up in the system.

```twig
{% for discount in craft.commerce.discounts.allDiscounts %}
{{ discount.name }} - {{ discount.description }}
{% endfor %}
```

## craft.commerce.discounts.getDiscountByCode(code)

Returns a discount that matches the code supplied.

```twig
{% set discount = craft.commerce.discount.getDiscountByCode('HALFOFF')
 %}
{% if discount %}
{{ discount.name }} - {{ discount.description }}
{% endif %}
```

## craft.commerce.sales.allSales

Returns an array of all sales set up in the system.

```twig
{% for sale in craft.commerce.allSales %}
{{ sale.name }} - {{ sale.description }}
{% endfor %}
```
