# Saving payment sources

If you’re using a [gateway](payment-gateways.md) that supports storing payment information, there are two ways to store the payment information.

You can either take any payment form and add a `savePaymentSource` parameter to it with a value of `true`.

```twig
{% if cart.gateway.supportsPaymentSources() %}
<div class="checkbox">
<label><input type="checkbox" name="savePaymentSource" value="1" /> {{ "Save card for future purchases"|t }}</label>
</div>
{% endif %}
```

Alternatively you can submit any regular gateway payment form to `commerce/payment-sources/add` instead of `commerce/payments/pay`.

Doing it the first way will pay for the order as well as store the card for future payments, while the second way will just save the payment information for future use.

It’s important to understand that this is only possible if the selected payment gateway supports storing payment sources.
