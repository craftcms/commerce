<?php
namespace Commerce\Gateways\Omnipay;

class Dummy_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'Dummy';
    }

	public function cpPaymentsEnabled()
	{
		return true;
	}

}