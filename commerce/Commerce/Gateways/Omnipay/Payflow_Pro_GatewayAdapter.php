<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Payflow_Pro_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Payflow_Pro';
    }
}