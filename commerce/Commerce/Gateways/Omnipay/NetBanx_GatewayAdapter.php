<?php
namespace Commerce\Gateways\Omnipay;

class NetBanx_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'NetBanx';
    }
}