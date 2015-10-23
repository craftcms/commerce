<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class FirstData_Connect_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'FirstData_Connect';
    }
}