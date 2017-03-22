<?php
namespace Commerce\Gateways\Omnipay;

class Eway_Rapid_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'Eway_Rapid';
    }
}