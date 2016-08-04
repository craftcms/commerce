<?php
namespace Commerce\Gateways\Omnipay;

class NetBanx_Hosted_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'NetBanx_Hosted';
    }
}