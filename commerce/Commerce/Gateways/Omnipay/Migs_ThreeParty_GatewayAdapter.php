<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Migs_ThreeParty_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Migs_ThreeParty';
    }
}