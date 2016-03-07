<?php
namespace Commerce\Gateways\Omnipay;

class PayPal_Express_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'PayPal_Express';
    }
}