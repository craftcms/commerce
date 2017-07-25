<?php

namespace craft\commerce\gateways;

class Dummy_GatewayAdapter extends CreditCardGatewayAdapter
{

    public $dummyProperty;

    public function handle()
    {
        return 'Dummy';
    }

    public function cpPaymentsEnabled()
    {
        return true;
    }
}