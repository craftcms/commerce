<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class TwoCheckout_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'TwoCheckout';
    }
}