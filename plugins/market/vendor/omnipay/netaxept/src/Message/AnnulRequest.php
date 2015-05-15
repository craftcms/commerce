<?php

namespace Omnipay\Netaxept\Message;

use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Message\AbstractRequest;

/**
 * Netaxept Annul Request
 *
 * @author Antonio Peric-Mazar <antonio@locastic.com>
 */
class AnnulRequest extends PurchaseRequest
{
    public function getData()
    {
        $data = array();
        $data['transactionAmount'] = $this->getAmountInteger();
        $data['transactionId'] = $this->getTransactionId();
        $data['merchantId'] = $this->getMerchantId();
        $data['token'] = $this->getPassword();
        $data['operation'] = 'ANNUL';

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
