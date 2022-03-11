# Upgrading to Commerce 4

::: warning
If you’re upgrading from Commerce 2, see the [Changes in Commerce 3](https://craftcms.com/docs/commerce/3.x/upgrading.html) and upgrade to the latest Commerce 3 version before upgrading to Commerce 4.
:::

## Preparing for the Upgrade

Before you begin, make sure that:

1. You’ve reviewed the [changes in Commerce 4](https://github.com/craftcms/commerce/blob/master/CHANGELOG.md#400)
2. Your site’s running at least **Craft 4.0** and **the latest version of Commerce 3**
3. Your **database and files are backed up** in case everything goes horribly wrong

Once you’ve completed these steps, you’re ready continue.

When upgrading from Commerce 3 to Commerce 4, the following changes may be important depending on how you’ve set up your project.


## Form Requests and Responses (Front-end)

Ajax responses from `commerce/payment-sources/*` no longer return the payment form error using the `paymentForm` key. Use `paymentFormErrors` to get the payment form errors instead.

## Payment forms

Payment forms are now namespaced with `paymentForm` and the gateway's `handle`. This is to prevent conflicts between normal fields and fields required by the gateway.

For example if you were outputting the payment form on the final step of your checkout you would need to make the following change:

```
// Commerce 3
{{ cart.gateway.getPaymentFormHtml(params)|raw }}

// Commerce 4
{% namespace cart.gateway.handle|commercePaymentFormNamespace %}
    {{ cart.gateway.getPaymentFormHtml(params)|raw }}
{% endnamespace %}
```

With this change you are now able to display multiple payment forms on the same page within the same form tag.