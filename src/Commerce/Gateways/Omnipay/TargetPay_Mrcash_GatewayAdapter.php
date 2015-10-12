<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class TargetPay_Mrcash_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'TargetPay_Mrcash';
    }
}