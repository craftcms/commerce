<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Coinbase_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Coinbase';
    }
}