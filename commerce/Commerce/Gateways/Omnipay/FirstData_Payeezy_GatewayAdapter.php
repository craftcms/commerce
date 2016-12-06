<?php
namespace Commerce\Gateways\Omnipay;

class FirstData_Payeezy_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'FirstData_Payeezy';
    }
}