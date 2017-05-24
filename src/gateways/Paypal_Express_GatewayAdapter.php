<?php

namespace craft\commerce\gateways;

use Omnipay\PayPal\PayPalItemBag;

class PayPal_Express_GatewayAdapter extends OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'PayPal_Express';
    }

    public function createItemBag()
    {
        return new PayPalItemBag();
    }
}