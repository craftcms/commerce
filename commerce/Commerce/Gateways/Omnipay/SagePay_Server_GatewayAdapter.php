<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class SagePay_Server_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'SagePay_Server';
    }
}