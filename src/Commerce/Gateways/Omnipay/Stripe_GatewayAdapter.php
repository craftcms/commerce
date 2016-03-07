<?php
namespace Commerce\Gateways\Omnipay;

class Stripe_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'Stripe';
    }
}