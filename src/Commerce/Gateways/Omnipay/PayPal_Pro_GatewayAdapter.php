<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class PayPal_Pro_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'PayPal_Pro';
    }
}