# General Configuration

In addition to the settings available in Commerce → Settings, the config items below can be placed into a `commerce.php` file in your `craft/config/` folder. Use the same format as `config/general.php`. You may define it in one of the environment config arrays, depending on which environment(s) you want the setting to apply to.

For example, if you want to change the Commerce inactive carts duration in dev environments, but not on staging or production environments, do this:

```php{4,10}
return [
    // Global settings
    '*' => [
        // ...
    ],

    // Dev environment settings
    'dev' => [
        'purgeInactiveCartsDuration' => P1D,
        // ...
    ],

    // Staging environment settings
    'staging' => [
        // ...
    ],

    // Production environment settings
    'production' => [
        // ...
    ],
];
```

Here’s the full list of config settings that Commerce supports:

## `autoSetNewCartAddresses`

Determines whether the customer’s last used shipping and billing addresses should automatically be set on new carts.

Default: `true`

## `activeCartDuration`

A [duration interval](https://en.wikipedia.org/wiki/ISO_8601#Durations) that determines how long a cart should go without being updated before it is listed as inactive in the Orders index page.

Default: `'PT1H'` (1 hour)


## `gatewayPostRedirectTemplate`

The path to the template that should be used to perform POST requests to offsite payment gateways.

The template must contain a form that posts to the URL supplied by the `actionUrl` variable, and outputs all hidden inputs with the `inputs` variable.

```twig
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Redirecting...</title>
</head>
<body onload="document.forms[0].submit();">
<form action="{{ actionUrl }}" method="post">
    <p>Redirecting to payment page...</p>
    <p>
        {{ inputs|raw }}
        <input type="submit" value="Continue">
    </p>
</form>
</body>
</html>
```

::: tip
Since this template is simply used for redirecting, it only appears for a few seconds, so we suggest making it load fast with minimal images and inline styles to reduce HTTP requests.
:::

A barebones template is used  by default if this setting isn’t set.

## `mergeLastCartOnLogin`

Determines whether a user’s previous cart should be merged with the active cart after they log in.

Default: `true`

## `pdfAllowRemoteImages`

Determines whether order PDFs can include remote images.

Default `false`

## `pdfPaperSize`

The size of the paper to use for generated order PDFs. A full list of paper size values can be found [here](https://github.com/dompdf/dompdf/blob/master/src/Adapter/CPDF.php#L45).

Default: `'letter'`

## `pdfPaperOrientation`

The orientation of the paper to use for generated order PDF files. Valid values are `'portrait'` or `'landscape'`.

Default: `'portrait'`

## `purgeInactiveCarts`

Whether Commerce should automatically delete inactive carts from the database during garbage collection.

Default: `true`

::: tip
You can control how long a cart should go without being updated before it gets deleted [`purgeInactiveCartsDuration`](#purgeinactivecartsduration) setting.
:::

## `purgeInactiveCartsDuration`

A [duration interval](https://en.wikipedia.org/wiki/ISO_8601#Durations) that determines how long a cart should go without being updated before it gets deleted during garbage collection.

Default: `'P3M'` (3 months)

## `requireBillingAddressAtCheckout`

Determines whether a billing address is required before making a payment on an order.

Default: `false`

## `requireShippingAddressAtCheckout`

Determines whether a shipping address is required before making a payment on an order.

Default: `false`

## `requireShippingMethodSelectionAtCheckout`

Determines whether a shipping method selection is required before making a payment on an order.

Default: `false`

## `updateBillingDetailsUrl`

The URL for a user to resolve billing issues with their subscription. 

Default: `''`

::: tip
The [example templates](example-templates.md) folder contains an example of this page. It can be found at `templates/shop/services/update-billing-details.html`
:::

## `useBillingAddressForTax`

Determines whether taxes should be calculated based on the billing address, as opposed to the shipping address.

Default: `false` (use the shipping address)
