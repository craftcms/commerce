<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class PayFast_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'PayFast';
    }
}