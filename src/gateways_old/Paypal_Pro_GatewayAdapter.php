<?php

namespace craft\commerce\gateways;

class Paypal_Pro_GatewayAdapter extends CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'PayPal_Pro';
    }
}