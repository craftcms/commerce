# Payment Currencies

Many customers feel more confident buying from your store if you allow them to purchase items in the same currency as their credit card or bank account.

Craft Commerce allows you to accept payments in other currencies you define. All products are entered and stored in the primary store currency you set up. Additional payment currencies can then be added which provide a conversion ratio based on the primary store currency. This shifts the exchange rate they pay from being discovered on their credit card statement after payment, to a known amount during checkout.

## Example

If you selected US Dollars (USD) as your store’s primary currency, you would enter all products, sales, discounts and orders in that currency.

Then you could add Australian Dollars (AUD) as an additional accepted payment currency, with a conversion ratio of `1.3`.

If a customer chooses to pay with AUD, an order that would have been $10.00 USD becomes $13.00 AUD.

::: tip
Craft Commerce does not keep your store’s exchange rates updated automatically. A plugin could be written to update the currency at your preferred interval.
:::

## Order Currency Fields

A cart (order) has the following fields relating to currency:

### `order.currency`

This is the primary store currency, and the currency that all the values for price, line items, adjustments, discounts etc are all stored in, and returned in.

### `order.paymentCurrency`

This is the currency the customer has currently selected as their payment currency. If your store only has a single currency, this will be set to the same as your primary store currency. A customer can change this currency, see [switching currencies](#switching-currencies).

## Transactions Currency Fields

When a customer makes a payment on the order, transactions are applied against the order. Transactions have the following fields relating to payment and currencies:

### `transaction.currency`

This is the primary store currency, and the currency that the transaction `amount` is stored in.

### `transaction.paymentCurrency`

This is the currency that the `paymentAmount` is stored as. It is also the currency that was used when communicating with the payment gateway when the customer was making payment.

### `transaction.paymentRate`

This is a snapshot of the payment currency’s conversion rate at the time of making payment. Because the conversion rate may have changed since making this payment.

## Switching currencies

The customer be switched to a different currency in the following ways.

1) The PHP constant `COMMERCE_PAYMENT_CURRENCY` is set to a 3-digit ISO code that corresponds to a payment currency you have set up. Having this constant set will lock the cart’s payment currency to this currency code. You would most likely set this constant in your `index.php` file in a similar location to your `CRAFT_LOCALE` constant.

2) Using the `commerce/cart/update-cart` form action, you can set POST param to named `paymentCurrency` to a valid 3-digit ISO code. This will have no affect if you have set the `COMMERCE_PAYMENT_CURRENCY` constant.

3) Using the `commerce/payments/pay` form action, you can set POST param to named `paymentCurrency` to a valid 3-digit ISO code. This will also have no affect if you have set the `COMMERCE_PAYMENT_CURRENCY` constant.

## Conversion and currency formatting

You can use the `|commerceCurrency` filter as a drop-in replacement for the `|currency` filter. But in addition to currency _formatting_, it can also be used for currency _conversion_, by setting the `convert` param to `true`. In addition to that, the currency formatting can also be disabled by setting the `format` param to `false`, if you just want to get the raw converted currency value as a float.

Examples:

If the store currency is USD and the order’s payment currency is AUD with a exchange rate of 1.3

```
{{ 10.00|commerceCurrency(cart.currency)}} // US$ 10.00

{{ order.totalPrice|commerceCurrency(cart.paymentCurrency,convert=true)}} // A$ 13.00

{{ order.totalPrice|commerceCurrency(cart.paymentCurrency,convert=true,format=false)}} // 13.0000

{{ order.totalPrice|commerceCurrency(cart.paymentCurrency,convert=true,format=true)}} // A$ 13.00
```

See [Twig Filters](twig-filters.md) form documentation on the `commerceCurrency` filter.
