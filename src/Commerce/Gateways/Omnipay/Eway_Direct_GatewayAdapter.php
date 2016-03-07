<?php
namespace Commerce\Gateways\Omnipay;

class Eway_Direct_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'Eway_Direct';
    }
}