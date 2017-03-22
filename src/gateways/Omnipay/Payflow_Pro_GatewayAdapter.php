<?php
namespace Commerce\Gateways\Omnipay;

class Payflow_Pro_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'Payflow_Pro';
    }
}