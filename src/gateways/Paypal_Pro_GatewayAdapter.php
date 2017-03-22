<?php
namespace craft\commerce\gateways;

class PayPal_Pro_GatewayAdapter extends CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'PayPal_Pro';
    }
}