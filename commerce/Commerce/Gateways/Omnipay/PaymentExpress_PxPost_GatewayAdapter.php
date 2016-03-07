<?php
namespace Commerce\Gateways\Omnipay;

class PaymentExpress_PxPost_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'PaymentExpress_PxPost';
    }
}