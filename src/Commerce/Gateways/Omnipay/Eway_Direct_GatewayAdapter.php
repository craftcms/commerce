<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Eway_Direct_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Eway_Direct';
    }
}