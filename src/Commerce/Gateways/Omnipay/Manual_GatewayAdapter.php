<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Manual_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Manual';
    }

    public function requiresCreditCard()
    {
        return false;
    }
}