<?php
namespace Commerce\Gateways\Omnipay;

use Omnipay\PayPal\PayPalItemBag;

class PayPal_Express_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
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