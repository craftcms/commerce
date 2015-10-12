<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\BaseGatewayAdapter;

class CardSave_GatewayAdapter extends BaseGatewayAdapter
{
    public function handle()
    {
        return 'CardSave';
    }
}