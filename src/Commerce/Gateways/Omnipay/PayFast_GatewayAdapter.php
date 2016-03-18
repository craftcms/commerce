<?php
namespace Commerce\Gateways\Omnipay;

class PayFast_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'PayFast';
    }
}