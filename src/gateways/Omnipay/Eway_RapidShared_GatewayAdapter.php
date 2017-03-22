<?php
namespace Commerce\Gateways\Omnipay;

class Eway_RapidShared_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'Eway_RapidShared';
    }
}