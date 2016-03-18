<?php
namespace Commerce\Gateways\Omnipay;

class WorldPay_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'WorldPay';
    }
}