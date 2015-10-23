<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class TargetPay_Directebanking_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'TargetPay_Directebanking';
    }
}