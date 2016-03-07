<?php
namespace Commerce\Gateways\Omnipay;

class Eway_RapidDirect_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'Eway_RapidDirect';
    }
}
