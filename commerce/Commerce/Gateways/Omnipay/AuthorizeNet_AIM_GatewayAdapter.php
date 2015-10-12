<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class AuthorizeNet_AIM_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'AuthorizeNet_AIM';
    }
}