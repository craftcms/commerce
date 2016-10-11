<?php
namespace Commerce\Gateways\Omnipay;

class SagePay_Server_GatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'SagePay_Server';
    }

    public function useNotifyUrl()
    {
        return true;
    }
}