<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class MultiSafepay_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'MultiSafepay';
    }
}