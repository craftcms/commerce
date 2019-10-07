# Payment Gateways

In Commerce 2 gateways are now provided via plugins, as opposed to Commerce 1, where all supported gateways were bundled within Commerce itself.

To create a payment gateway you must install the appropriate plugin, then go to Commerce → Settings → Gateways and set up the appropriate gateway. For more detailed instructions, see each plugin’s `README.md` file.

Payment gateways generally fit in one of two categories:

- External gateways, or offsite gateways.
- Merchant-hosted gateways, or onsite gateways.

Merchant hosted gateways let you to collect the customer’s credit card details directly on your site, but have much stricter requirements, such as an SSL certificate for your server. You will also be subject to much more rigorous security requirements under the PCI DSS (Payment Card Industry Data Security Standard). These security requirements are your responsibility, but some gateways allow payment card tokenization.

The following is a table of gateways provided by first-party plugins.

| Plugin                           | Gateways                                | Remarks                                                            | 3D Secure Support   |
|----------------------------------|-----------------------------------------|--------------------------------------------------------------------|---------------------|
| `craftcms/commerce-stripe`       | Stripe                                  | Uses Stripe SDK; only first-party gateway to support subscriptions | Yes                 |
| `craftcms/commerce-paypal`       | PayPal Pro; PayPal REST; PayPal Express | PayPal REST supports storing payment information                   | Only PayPal Express |
| `craftcms/commerce-sagepay`      | SagePay Direct; SagePay Server          | SagePay Direct requires setting up webhooks                        | Yes                 |
| `craftcms/commerce-multisafepay` | MultiSafePay REST                       | Does not support authorize charges                                 | Yes                 |
| `craftcms/commerce-worldpay`     | Worldpay JSON                           | -                                                                  | No                  |
| `craftcms/commerce-eway`         | eWAY Rapid                              | Supports storing payment information                               | Yes                 |
| `craftcms/commerce-mollie`       | Mollie                                  | Does not support authorize charges                                 | Yes                 |

## Dummy Gateway

After installing Commerce, the plugin will install some demo products and a basic config. It will also install a Dummy payment gateway
that can be used for testing.

This is a dummy gateway driver intended for testing purposes. If you provide a valid card number ending in an even number, the gateway will return a success response. If it ends in an odd number, the driver will return a generic failure response. For example:

- `4242424242424242` - Success
- `4444333322221111` - Failure

## Manual Gateway

The manual payment gateway is a special gateway that does not communicate with any third party.

When you need to accept cheque or bank deposit payments, you should use the manual payment gateway.

The gateway simply authorizes all payments, allowing the order to proceed. You may then manually mark the payment as captured in the Control Panel when payment is received.

## Other gateway specifics

Before using gateways provided by plugins, make sure to consult the plugin’s readme for specifics pertaining to the gateway.

## Adding additional gateways

Additional payment gateways can be added to Commerce with relatively little work. All the gateways mentioned above with the exception of Stripe, use the Omnipay payment library and can be used as point of reference when creating your own.

## Storing config outside of the database

If you do not wish to store your payment gateway config information in the database (which could include secret API keys), you can override the values of a payment method’s setting via the `commerce-gateways.php` config file. Use the payment gateway’s handle as the key to the config for that payment method. Note that you still need to configure the gateway in the Control Panel first in order to reference the gateway’s handle within this config file.

```php
return [
  'myStripeGateway' => [
    'apiKey' => getenv('STRIPE_API_KEY'),
  ],
];
```

## Payment sources

Commerce 2 supports storing payment sources for select gateways. Storing a payment source allows for a more streamlined shopping experience for your customers.

The following first-party provided gateways support payment sources:

- Stripe
- PayPal REST
- eWAY Rapid

## Updating from Commerce 1.x

When you update to Commerce 2, your gateways will still show up in the admin panel, but they will not work. To restore operation to them, you have to install the appropriate plugin. If such a plugin does not exist, you will have to build your own plugin or switch to a different gateway.

## 3D Secure payments

3D Secure payments add another authentication step for payments. If a payment has been completed using 3D Secure authentication, the liability for fraudulent charges is shifted from the merchant to the card issuer.
Support for this feature depends on the gateway used and its settings.

## Partial refunds

All first-party provided gateways support partial refunds as of Commerce 2.0.
