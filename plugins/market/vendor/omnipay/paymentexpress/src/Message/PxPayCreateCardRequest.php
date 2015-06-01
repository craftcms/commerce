<?php

namespace Omnipay\PaymentExpress\Message;

/**
 * PaymentExpress PxPost Create Credit Card Request
 */
class PxPayCreateCardRequest extends PxPayAuthorizeRequest
{
    public function getData()
    {
        $this->setAmount('1.00');
        $this->setCurrency('NZD');

        $data = parent::getData();
        $data->EnableAddBillCard = 1;

        return $data;
    }
}
