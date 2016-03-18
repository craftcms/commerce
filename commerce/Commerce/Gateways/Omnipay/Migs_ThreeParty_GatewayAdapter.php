<?php
namespace Commerce\Gateways\Omnipay;

class Migs_ThreeParty_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'Migs_ThreeParty';
    }
}