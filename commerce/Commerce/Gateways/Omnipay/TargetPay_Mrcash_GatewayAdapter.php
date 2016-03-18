<?php
namespace Commerce\Gateways\Omnipay;

class TargetPay_Mrcash_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'TargetPay_Mrcash';
    }
}