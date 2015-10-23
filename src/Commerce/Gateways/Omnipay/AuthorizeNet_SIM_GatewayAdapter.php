<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class AuthorizeNet_SIM_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'AuthorizeNet_SIM';
    }
}