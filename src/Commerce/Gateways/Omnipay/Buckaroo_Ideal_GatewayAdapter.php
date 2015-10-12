<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Buckaroo_Ideal_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Buckaroo_Ideal';
    }
}