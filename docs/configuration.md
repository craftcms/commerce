# General Configuration

In addition to the settings available in Commerce → Settings, the config items below can be placed into a `commerce.php` file in your `craft/config/` folder:

## `pdfAllowRemoteImages`

Determines if a Dompdf PDF render will allow remote images.

Default `false`

## `autoSetNewCartAddresses`

Determines whether the customer’s last used shipping and billing addresses should automatically be set on new carts.

Can be set to `true` or `false` (default is `true`).

How long the cookie storing the cart should last. The cart exists independently of the Craft user’s session.

## `gatewayPostRedirectTemplate`

Allows for the overriding of the template used to perform POST redirects to the payment gateway.

The template path that this item points to must contain a form that submits itself to the `actionUrl` variable, and outputs all hidden inputs with the `inputs` variable. Below is an example template:

```twig
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Redirecting...</title>
</head>
<body onload="document.forms[0].submit();">
<form action="{{ actionUrl|raw }}" method="post">
    <p>Redirecting to payment page...</p>
    <p>
        {{ inputs|raw }}
        <input type="submit" value="Continue" />
    </p>
</form>
</body>
</html>
```

Since this template is simply used for redirecting, it only appears for a few seconds, so we suggest making it load fast with minimal images and inlined styles to reduce http requests.

## `pdfPaperSize`

Default: `letter`

The size of the paper to use for generated order PDF files (letter, legal, A4, etc.).  A full list of paper size values can be found [here](https://github.com/dompdf/dompdf/blob/master/src/Adapter/CPDF.php#L45).

## `pdfPaperOrientation`

Default: `portrait`

The orientation of the paper to use for generated order PDF files. Valid values are `portrait` or `landscape`.

## `purgeInactiveCarts`

Default: `true`

Should Commerce purge old inactive carts from the database. See the [`purgeInactiveCartsDuration`](#purgeInactiveCartsDuration) setting to control how old the cart needs to be.

## `purgeInactiveCartsDuration`

A php [Date Interval](http://php.net/manual/en/class.dateinterval.php)
Default: 3 months. (`P3M`).

Inactive carts older than this interval from their last update will be purged (deleted).

::: tip
The interval check for purging of inactive carts is only run when visiting the Orders index page in the Control Panel.
:::

## `requireBillingAddressAtCheckout`

Determines whether the billing address needs to exist on the cart in order to submit successfully to the `commerce/payment/pay` action.

Can be set to `true` or `false` (default is `false`).

## `requireShippingAddressAtCheckout`

Determines whether payment requests made to the `commerce/payments/pay` controller action requires a shipping address to be present on an order before attempting payment.

Can be set to `true` or `false` (default is `false`).

## `requireShippingMethodSelectionAtCheckout`

Determines whether payment requests made to the `commerce/payments/pay` controller action requires a shipping method selection to be present on an order before attempting payment.

Can be set to `true` or `false` (default is `false`).

## `useBillingAddressForTax`

Determines whether to use the billing address of the cart to calculate taxes. By default the shipping address is used for tax calculations.

Can be set to `true` or `false` (default is `false`).

