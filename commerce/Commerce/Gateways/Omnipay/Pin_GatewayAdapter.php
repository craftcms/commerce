<?php
namespace Commerce\Gateways\Omnipay;

class Pin_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'Pin';
    }
}