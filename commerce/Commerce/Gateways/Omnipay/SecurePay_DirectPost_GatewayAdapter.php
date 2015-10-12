<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class SecurePay_DirectPost_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'SecurePay_DirectPost';
    }
}