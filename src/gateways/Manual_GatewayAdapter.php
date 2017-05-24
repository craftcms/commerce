<?php

namespace craft\commerce\gateways;

class Manual_GatewayAdapter extends OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'Manual';
    }
}