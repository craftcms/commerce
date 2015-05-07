<?php

namespace Omnipay\PaymentExpress\Message;

use Omnipay\Common\Message\AbstractResponse;

/**
 * PaymentExpress Response
 */
class Response extends AbstractResponse
{
    public function isSuccessful()
    {
        return 1 === (int) $this->data->Success;
    }

    public function getTransactionReference()
    {
        return empty($this->data->DpsTxnRef) ? null : (string) $this->data->DpsTxnRef;
    }

    public function getCardReference()
    {
        if (! empty($this->data->Transaction->DpsBillingId)) {
            return (string) $this->data->Transaction->DpsBillingId;
        } elseif (! empty($this->data->DpsBillingId)) {
            return (string) $this->data->DpsBillingId;
        }

        return null;
    }

    public function getMessage()
    {
        if (isset($this->data->HelpText)) {
            return (string) $this->data->HelpText;
        } else {
            return (string) $this->data->ResponseText;
        }
    }
}
