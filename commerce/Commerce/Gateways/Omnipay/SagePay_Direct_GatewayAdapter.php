<?php
namespace Commerce\Gateways\Omnipay;

class SagePay_Direct_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'SagePay_Direct';
    }
}