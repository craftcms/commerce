<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class PaymentExpress_PxPay_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'PaymentExpress_PxPay';
    }
}