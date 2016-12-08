<?php
namespace Commerce\Gateways\Omnipay;

class MultiSafepay_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'MultiSafepay';
    }
}