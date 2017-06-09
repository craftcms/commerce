<?php
namespace Commerce\Gateways\Omnipay;

class Buckaroo_CreditCard_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'Buckaroo_CreditCard';
    }
}