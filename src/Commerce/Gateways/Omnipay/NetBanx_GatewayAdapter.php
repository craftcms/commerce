<?php
namespace Commerce\Gateways\Omnipay;

class NetBanx_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'NetBanx';
    }
}