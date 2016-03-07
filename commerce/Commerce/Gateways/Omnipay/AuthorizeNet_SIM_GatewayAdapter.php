<?php
namespace Commerce\Gateways\Omnipay;

class AuthorizeNet_SIM_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'AuthorizeNet_SIM';
    }
}