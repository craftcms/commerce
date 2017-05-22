<?php
namespace Commerce\Gateways\Omnipay;

use Commerce\Gateways\PaymentFormModels\WorldpayJsonPaymentFormModel;
use Craft\BaseModel;
use Omnipay\Common\CreditCard;

class WorldPay_Json_GatewayAdapter extends \Commerce\Gateways\CreditCardGatewayAdapter
{
    public function handle()
    {
        return 'WorldPay_Json';
    }

    public function getPaymentFormModel()
    {
        return new WorldpayJsonPaymentFormModel();
    }

    public function populateCard(CreditCard $card, BaseModel $paymentForm)
    {
    }

    public function cpPaymentsEnabled()
    {
        return false;
    }
}