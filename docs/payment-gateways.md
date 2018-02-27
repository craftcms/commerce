# Payment Gateways
Craft Commerce can be used with over 20+ payment gateways out of the box, through the use of the [Omnipay](https://github.com/thephpleague/omnipay) PHP library. Additional Omnipay gateways not included in the standard Craft Commerce install can be added with a [plugin](#adding-additional-gateways).

All included gateways should work as expected, but logistically we are unable to test them all. See our [testing matrix](https://craftcommerce.com/support/which-payment-gateways-do-you-support) for more information. 

To set up a new payment method within your CP, go to`Commerce > Settings > Payment Methods`. Each payment methods' gateway requires different settings, which you will need to obtain from your gateway provider.

Payment gateways can be one of two categories:

- External gateways, or Off-site gateways.
- Merchant-hosted gateways, or On-site gateways.

Merchant hosted gateways let you to collect the customer's credit card details directly on your site, but have much stricter requirements, such as an SSL certificate for your server. You will also be subject to much more rigorous security requirements under the PCI DSS (Payment Card Industry Data Security Standard). These security requirements are your responsibility.

The following Omnipay gateways are included the standard installation of Craft Commerce.

- 2checkout
- Authorize.net
- Buckaroo
- Cardsave
- Coinbase
- Dummy
- eWAY
- First Data
- GoCardless
- Manual
- MIGS
- Mollie
- MultiSafepay
- Netaxept
- Netbanx
- PayFast
- Payflow
- Payment Express
- Paypal
- Pin
- SagePay
- Securepay
- Stripe
- TargetPay
- Worldpay

To see the levels of support for each gateway see this [support article](https://craftcommerce.com/support/which-payment-gateways-do-you-support).

# Adding additional gateways

Additional Onmipay gateways can be added to Craft Commerce. They require the creation of a plugin that wraps the Omnipay gateway class with a Commerce GatewayAdapter. An example plugin can be found [here](https://github.com/lukeholder/craftcommerce-ogone).

# Storing config outside of the database

If you do not wish to store your payment gateway config information in the database (which could include secret api keys), you can override the values of a payment method's setting by placing a 'paymentMethodSettings' key into your commerce.php config file. You then use the payment method's ID  as the key to the config for that payment method.

```php
return [
	'paymentMethodSettings' => [
		'2' => [
			'apiKey' => getenv('STRIPE_API_KEY'),
		],
	],
];
```

# CSRF Protection issues

Craft CMS [supports CSRF protection](https://craftcms.com/support/csrf-protection) when turned on. Some gateways attempt to POST data back to Craft Commerce which they can't do with a valid token. If you wish to have CSRF protection enabled on your site and your gateway uses a POST request when communicating with Craft Commerce, you will need to disable CSRF protection for that request.

To learn how to disable CSRF on a per controller action basis, see this [Stack Overflow answer](http://craftcms.stackexchange.com/a/4554/91). 

# Dummy Gateway

After installing Commerce, the plugin will install some demo products and a basic config. It will also install a Dummy payment gateway
that can be used for testing.

This is a dummy gateway driver intended for testing purposes. If you provide a valid card number ending in an even number, the gateway will return a success response. If it ends in an odd number, the driver will return a generic failure response. For example:

- `4929000000006` - Success
- `4444333322221111` - Failure

For general usage instructions, please see the main Omnipay repository.

# Paypal Express

### Important
If you're going to use the PayPal Express payment gateway you are required to change the default value of ```tokenParam``` in your
[Craft config](https://docs.craftcms.com/api/v3/craft-config-generalconfig.html#$tokenParam-detail)

Choose any different token name other than ```token```, for example you could put ```craftToken```. Otherwise redirects from PayPal will fail.

PayPal Express Checkout requires an API Username, Password, and Signature. These are different from your PayPal account details. You can obtain your API details by logging in to your PayPal account, and clicking Profile > My Selling Tools > API Access > Request/View API Credentials > Request API Signature.

>{Warning} Paypal have increased their TLS requirements, which affects MAMP 3 and some OSX users. If you are affected, you will see an error relating to "SSL" when attempting to pay with paypal. Upgrading to MAMP 4 should fix the issue. Read more here: https://github.com/paypal/TLS-update#php

# Manual Gateway

The manual payment gateway is a special gateway that does not communicate with any 3rd party.

When you need to accept cheque or bank deposit payments, you should use the manual payment gateway.

The gateway simply authorizes all payments, allowing the order to proceed. You may then manually mark the payment as "captured" in the control panel when payment is received.

>{Tip} When creating a Manual payment method, you must select the payment type to be "Authorize Only".

# Worldpay Json

The Worldpay Json gateway is the newly recommended modern gateway API for Worldpay. The 'Worldpay' gateway below is the older offsite gateway API.

The Worldpay Json gateway uses client side javascript `worldpay.js` on your payment template page to generate a token representing the credit card. This token can be passed to the standard `commerce/payments/pay` form action like the Stripe gateway.

You have the option of a simple implementation using the `worldpay.js` 'Template Form' documented [here](https://developer.worldpay.com/jsonapi/docs/template-form), and a more advanced customized implementation called 'Own Form' documented [here](https://developer.worldpay.com/jsonapi/docs/own-form).

The example templates that come with Craft Commerce include an example of the 'Template Form' method.

# Worldpay

WorldPay is an off-site payment gateway. You must make some changes in your WorldPay Merchant Admin Interface before it will work correctly:

- Log into your WorldPay Merchant Admin Interface
- Under Installations, click Setup next to your Installation ID
- In the Payment Response URL field, enter `<wpdisplay item=MC_callback>`
- Make sure the Payment Response enabled? option is enabled
- Make sure the Enable the Shopper Response option is enabled
- In the Payment Response password field, choose a password, and record this in your Craft Commerce payment method settings
- In the MD5 secret for transactions field, choose a password, and record this in your Craft Commerce payment method settings
- In your Craft Commerce payment method settings, set the 'Signature Fields' property to `instId:amount:currency:cartId`

# Authorize.net

The Authorize.net omnipay driver offers a more modern AIM xml based gateway (On-Site), as well as the SIM (Off-site) redirect-based gateway.

When configuring the AIM gateway, use the following endpoints:

Live: `https://api.authorize.net/xml/v1/request.api`
Developer: `https://apitest.authorize.net/xml/v1/request.api`

When configuring the SIM gateway, use the following endpoints:

Live: `https://secure2.authorize.net/gateway/transact.dll`
Developer: `https://test.authorize.net/gateway/transact.dll`

# Payment Express	PxPay

Payment Express PxPay is an offsite redirect gateway.

When setting up the payment method for this gateway and entering credentials, only use `Username` and `Password` fields. Don't use the `Px Post Username` and `Px Post Password` fields.
