<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class WorldPay_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'WorldPay';
    }
}