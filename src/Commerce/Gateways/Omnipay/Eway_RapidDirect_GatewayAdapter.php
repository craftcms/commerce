<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Eway_RapidDirect_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Eway_RapidDirect';
    }
}
