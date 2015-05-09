<?php

namespace Omnipay\AuthorizeNet\Message;

/**
 * Authorize.Net Refund Request
 */
class AIMRefundRequest extends AbstractRequest
{
    protected $action = 'CREDIT';

    public function getData()
    {
        $data = $this->getBaseData('RefundTransaction');

        $this->validate('amount', 'transactionReference');

        $data['x_trans_id'] = $this->getTransactionReference();
        $data['x_card_num'] = $this->getCard()->getNumber();
        $data['x_exp_date'] = $this->getCard()->getExpiryDate('my');
        $data['x_amount'] = $this->getAmount();

        return $data;
    }
}
