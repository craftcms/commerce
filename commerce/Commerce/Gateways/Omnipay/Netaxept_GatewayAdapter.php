<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Netaxept_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Netaxept';
    }
}