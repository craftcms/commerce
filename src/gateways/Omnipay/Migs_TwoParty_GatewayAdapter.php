<?php
namespace Commerce\Gateways\Omnipay;

class Migs_TwoParty_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'Migs_TwoParty';
    }
}