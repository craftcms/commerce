<?php
namespace Commerce\Gateways\Omnipay;

class AuthorizeNet_AIM_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'AuthorizeNet_AIM';
    }
}