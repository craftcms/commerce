<?php
namespace Commerce\Gateways\Omnipay;

class TargetPay_Directebanking_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'TargetPay_Directebanking';
    }
}