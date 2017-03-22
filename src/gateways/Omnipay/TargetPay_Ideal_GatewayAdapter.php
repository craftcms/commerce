<?php
namespace Commerce\Gateways\Omnipay;

class TargetPay_Ideal_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'TargetPay_Ideal';
    }
}