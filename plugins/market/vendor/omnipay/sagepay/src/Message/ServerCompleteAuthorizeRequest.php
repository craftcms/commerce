<?php

namespace Omnipay\SagePay\Message;

use Omnipay\Common\Exception\InvalidResponseException;

/**
 * Sage Pay Server Complete Authorize Request
 */
class ServerCompleteAuthorizeRequest extends AbstractRequest
{
    /**
     * Get the signature calculated from the three pieces of saved local
     * information:
     * * VendorTxCode - merchant site ID (aka transactionId).
     * * VPSTxId - SagePay ID (aka transactionReference)
     * * SecurityKey - SagePay one-use token.
     * and the POSTed transaction results.
     * Note that the three items above are passed in as a single JSON structure
     * as the transactionReference. Would be nice if that were just the fallback,
     * if not passed in as three separate items to the relevant fields.
     */
    public function getSignature()
    {
        $this->validate('transactionId', 'transactionReference');

        $reference = json_decode($this->getTransactionReference(), true);

        // Re-create the VPSSignature
        $signature_string =
            $reference['VPSTxId'].
            $reference['VendorTxCode'].
            $this->httpRequest->request->get('Status').
            $this->httpRequest->request->get('TxAuthNo').
            $this->getVendor().
            $this->httpRequest->request->get('AVSCV2').
            $reference['SecurityKey'].
            $this->httpRequest->request->get('AddressResult').
            $this->httpRequest->request->get('PostCodeResult').
            $this->httpRequest->request->get('CV2Result').
            $this->httpRequest->request->get('GiftAid').
            $this->httpRequest->request->get('3DSecureStatus').
            $this->httpRequest->request->get('CAVV').
            $this->httpRequest->request->get('AddressStatus').
            $this->httpRequest->request->get('PayerStatus').
            $this->httpRequest->request->get('CardType').
            $this->httpRequest->request->get('Last4Digits').
            // New for protocol v3.00
            // Described in the docs as "mandatory" but not supplied when PayPal is used,
            // so provide the defaults.
            $this->httpRequest->request->get('DeclineCode', '').
            $this->httpRequest->request->get('ExpiryDate', '').
            $this->httpRequest->request->get('FraudResponse', '').
            $this->httpRequest->request->get('BankAuthCode', '');

        return md5($signature_string);
    }

    /**
     * Get the POSTed data, checking that the signature is valid.
     */
    public function getData()
    {
        $signature = $this->getSignature();

        if (strtolower($this->httpRequest->request->get('VPSSignature')) !== $signature) {
            throw new InvalidResponseException;
        }

        return $this->httpRequest->request->all();
    }

    public function sendData($data)
    {
        return $this->response = new ServerCompleteAuthorizeResponse($this, $data);
    }
}
