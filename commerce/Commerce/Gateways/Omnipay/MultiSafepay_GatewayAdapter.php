<?php
namespace Commerce\Gateways\Omnipay;

class MultiSafepay_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'MultiSafepay';
    }
}