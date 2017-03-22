<?php
namespace Commerce\Gateways\Omnipay;

class Netaxept_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'Netaxept';
    }
}