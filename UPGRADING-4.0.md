# Upgrading to Commerce 4

::: warning
If you’re upgrading from Commerce 2, see
the [Changes in Commerce 3](https://craftcms.com/docs/commerce/3.x/upgrading.html) and upgrade to the latest Commerce 3
version before upgrading to Commerce 4.
:::

## Preparing for the Upgrade

Before you begin, make sure that:

1. You’ve reviewed the [changes in Commerce 4](https://github.com/craftcms/commerce/blob/master/CHANGELOG.md#400)
2. Your site’s running at least **Craft 4.0** and **the latest version of Commerce 3**
3. Your **database and files are backed up** in case everything goes horribly wrong

Once you’ve completed these steps, you’re ready continue.

When upgrading from Commerce 3 to Commerce 4, the following changes may be important depending on how you’ve set up your
project.

## Customer → User Transition

You no longer set an email on an order. All "customers" should be a user element (no matter the status). Previously you
would have used the setEmail method on an order but now you would use setUser or set the userId . This logic is
specifically left up to the controller or service to ensure the user exists etc

## Countries and States

Previously countries and states were manually added and removed in store settings. Craft 4 now has an addresses service
that provides a Country repository and associated Subdivison informformation (States, Provinces etc). Commerce has
removed the custom concept of managing countries and states.

Your enabled countries were migrated to store settings, and you can order and remove the list of countries available for
selection by your customers in front end in dropdowns:

craft.commerce.countries.allEnabledCountriesAsList → craft.commerce.store.store.getCountriesList()

States can no longer be enabled or disabled for selection in dropdown lists, but the new Order Address Condition allows
you to set rules for allowed addresses, and addresses submitted to the cart/order outside those rules will not be
allowed to be applied to carts/orders.

We have migrated your custom countries and states into custom fields on the addresses (done when you run the `php craft commerce/upgrade`
command), and also added rules to zone and store market condition builders to match those custom country and state
values. Please review your tax and shipping zones, and we encourage you to use real countries and administrative areas (
states) for your zones in the future.

## Form Requests and Responses (Front-end)

Ajax responses from `commerce/payment-sources/*` no longer return the payment form error using the `paymentForm` key.
Use `paymentFormErrors` to get the payment form errors instead.

## Payment forms

Payment forms are now namespaced with `paymentForm` and the gateway's `handle`. This is to prevent conflicts between
normal fields and fields required by the gateway.

For example if you were outputting the payment form on the final step of your checkout you would need to make the
following change:

```
// Commerce 3
{{ cart.gateway.getPaymentFormHtml(params)|raw }}

// Commerce 4
{% namespace cart.gateway.handle|commercePaymentFormNamespace %}
    {{ cart.gateway.getPaymentFormHtml(params)|raw }}
{% endnamespace %}
```

With this change you are now able to display multiple payment forms on the same page within the same form tag.