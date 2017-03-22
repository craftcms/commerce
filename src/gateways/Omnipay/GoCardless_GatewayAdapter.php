<?php
namespace Commerce\Gateways\Omnipay;

class GoCardless_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'GoCardless';
    }
}