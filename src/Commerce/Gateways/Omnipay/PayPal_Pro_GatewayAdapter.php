<?php
namespace Commerce\Gateways\Omnipay;

class PayPal_Pro_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'PayPal_Pro';
    }
}