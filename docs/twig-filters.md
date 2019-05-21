# Twig Filters

## |commerceCurrency

You can use the `|commerceCurrency` filter as a drop-in replacement for the built in Craft `|currency` filter. But in addition to currency _formatting_, it can also be used for currency _conversion_, by setting the `convert` param to `true`. In addition to that, the currency formatting can also be disabled by setting the `format` param to `false`, if you just want to get the raw converted currency value as a float.

### currency (string)

A valid payment currency

### convert (bool) default: `false`

Should the amount passed to this filter be converted to the exchange rate of the payment currency iso passed

### format (bool) default: `true`

Should the amount passed to this filter be formatted according to the payment currency iso passed. This will add the payment currency symbol to the amount and apply the corresponding thousands and decimal separators.

### stripZeros (bool) default: `false`

Should the amount passed have its minor unit zeros removed for a cleaner looking number.

### Examples:

```
{{ 10.00|commerceCurrency(cart.currency) }} // US$ 10.00

{{ order.totalPrice|commerceCurrency(cart.paymentCurrency,convert=true) }} // A$ 13.00

{{ order.totalPrice|commerceCurrency('AUD',convert=true,format=false) }} // 13.0000

{{ order.totalPrice|commerceCurrency('AUD',convert=true,format=true) }} // A$ 13.00

{{ order.totalPrice|commerceCurrency('AUD',convert=true,format=true,stripZeros=true) }} // A$ 13
```

You might want to show the order’s price in all available payment currencies:

```twig
{% for currency in craft.commerce.paymentCurrencies %}
    Total in {{ currency.iso|upper }}: {{ cart.totalPrice|commerceCurrency(cart.paymentCurrency,convert=true) }} <br>
{% endfor %}
</select>
```

