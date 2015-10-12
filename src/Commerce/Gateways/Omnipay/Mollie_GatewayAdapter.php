<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Mollie_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Mollie';
    }
}