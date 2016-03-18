<?php
namespace Commerce\Gateways\Omnipay;

class CardSave_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'CardSave';
    }
}