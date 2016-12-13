<?php
namespace Commerce\Gateways\Omnipay;

use Craft\BaseModel;
use Omnipay\Common\Message\AbstractRequest as OmnipayRequest;

class MultiSafepay_RestGatewayAdapter extends \Commerce\Gateways\OffsiteGatewayAdapter
{
    public function handle()
    {
        return 'MultiSafepay_Rest';
    }

    public function populateRequest(OmnipayRequest $request, BaseModel $paymentForm)
    {
        $request->setType('redirect');
    }
}