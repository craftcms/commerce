<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Stripe_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Stripe';
    }
}