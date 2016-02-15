<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Eway_RapidShared_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Eway_RapidShared';
    }
}