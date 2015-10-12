<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Pin_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Pin';
    }
}