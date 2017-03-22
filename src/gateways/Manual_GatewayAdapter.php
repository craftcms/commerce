<?php
namespace Commerce\Gateways\Omnipay;

class Manual_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'Manual';
    }
}