<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class Migs_TwoParty_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'Migs_TwoParty';
    }
}