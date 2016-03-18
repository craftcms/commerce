<?php
namespace Commerce\Gateways\Omnipay;

class TwoCheckout_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'TwoCheckout';
    }
}