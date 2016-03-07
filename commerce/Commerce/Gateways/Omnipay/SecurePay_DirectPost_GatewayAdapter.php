<?php
namespace Commerce\Gateways\Omnipay;

class SecurePay_DirectPost_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'SecurePay_DirectPost';
    }
}