<?php

namespace Omnipay\Eway\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RequestInterface;
use Omnipay\Common\Exception\InvalidResponseException;

/**
 * eWAY Direct Response
 */
class DirectResponse extends AbstractResponse
{
    public function isSuccessful()
    {
        return "True" === (string) $this->data->ewayTrxnStatus;
    }

    public function isRedirect()
    {
        return false;
    }

    public function getTransactionReference()
    {
        if (empty($this->data->ewayTrxnNumber)) {
            return null;
        }
        
        return (int) $this->data->ewayTrxnNumber;
    }

    public function getMessage()
    {
        return (string) $this->data->ewayTrxnError;
    }
}
