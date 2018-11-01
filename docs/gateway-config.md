
## Gateway Configuration

In addition to the settings available for each gateway in Commerce → Settings → Gateways, the config items below can be placed into a `commerce-gateways.php` file in your `craft/config` directory.

This allows for the overriding of gateway settings. You still need to configure the gateway in the Control Panel so you can reference the gateway’s `handle` as the key to the gateway’s settings.

Example:

```php
<?php
return [
    'ewayGatewayHandle' => [
        'testMode' => getenv('EWAY_TEST_MODE'),
        'apiKey' => getenv('EWAY_API_KEY'),
        'password' => getenv('EWAY_PASSWORD'),
        'CSEKey' => getenv('EWAY_CSE_KEY'),
    ],
    'paypalProGateway' => [
        'testMode' => getenv('PAYPAL_PRO_TEST_MODE'),
        'password' => getenv('PAYPAL_PRO_PASSWORD'),
        'username' => getenv('PAYPAL_PRO_USERNAME'),
        'signature' => getenv('PAYPAL_PRO_SIGNATURE'),
    ],
];
```
