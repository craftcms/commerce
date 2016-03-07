<?php
namespace Commerce\Gateways\Omnipay;

class Buckaroo_PayPal_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'Buckaroo_PayPal';
    }
}