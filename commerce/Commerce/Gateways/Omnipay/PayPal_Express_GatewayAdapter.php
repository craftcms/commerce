<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class PayPal_Express_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'PayPal_Express';
    }

    public function requiresCreditCard()
    {
        return false;
    }
}