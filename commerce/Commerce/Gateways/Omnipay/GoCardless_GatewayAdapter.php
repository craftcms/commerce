<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class GoCardless_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'GoCardless';
    }
}