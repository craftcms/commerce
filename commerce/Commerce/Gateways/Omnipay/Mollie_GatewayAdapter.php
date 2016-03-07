<?php
namespace Commerce\Gateways\Omnipay;

class Mollie_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'Mollie';
    }
}