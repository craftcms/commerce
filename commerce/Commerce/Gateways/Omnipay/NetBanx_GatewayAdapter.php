<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class NetBanx_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'NetBanx';
    }
}