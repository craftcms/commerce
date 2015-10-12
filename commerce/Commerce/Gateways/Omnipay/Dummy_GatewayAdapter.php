<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Dummy_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Dummy';
    }
}