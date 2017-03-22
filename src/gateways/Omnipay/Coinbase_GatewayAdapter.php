<?php
namespace Commerce\Gateways\Omnipay;

class Coinbase_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'Coinbase';
    }
}