<?php
namespace Commerce\Gateways\Omnipay;

class PaymentExpress_PxPay_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'PaymentExpress_PxPay';
    }
}