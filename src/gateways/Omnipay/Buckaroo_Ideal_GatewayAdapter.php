<?php
namespace Commerce\Gateways\Omnipay;

class Buckaroo_Ideal_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'Buckaroo_Ideal';
    }
}