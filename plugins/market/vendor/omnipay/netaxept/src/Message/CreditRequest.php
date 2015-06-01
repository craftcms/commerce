<?php

namespace Omnipay\Netaxept\Message;

use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\AbstractRequest;

/**
 * Netaxept Credit Request
 *
 * @author Antonio Peric-Mazar <antonio@locastic.com>
 */
class CreditRequest extends PurchaseRequest
{
    public function getData()
    {
        $data = array();
        $data['transactionAmount'] = $this->getAmountInteger();
        $data['transactionId'] = $this->getTransactionId();
        $data['merchantId'] = $this->getMerchantId();
        $data['token'] = $this->getPassword();
        $data['operation'] = 'CREDIT';

        if (empty($data['transactionAmount']) || empty($data['transactionId'])) {
            throw new InvalidResponseException;
        }

        return $data;
    }

    public function sendData($data)
    {
        $url = $this->getEndpoint().'/Netaxept/Process.aspx?';
        $httpResponse = $this->httpClient->get($url.http_build_query($data))->send();

        return $this->response = new Response($this, $httpResponse->xml());
    }
}
