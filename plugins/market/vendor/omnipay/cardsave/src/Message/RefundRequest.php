<?php

namespace Omnipay\CardSave\Message;

use DOMDocument;
use SimpleXMLElement;

/**
 * CardSave Purchase Request
 */
class RefundRequest extends PurchaseRequest
{
    public $transactionType = 'REFUND';

    public function getData()
    {
        $this->validate('transactionReference', 'amount', 'currency');

        $data = new SimpleXMLElement('<CrossReferenceTransaction/>');
        $data->addAttribute('xmlns', $this->namespace);

        $data->PaymentMessage->MerchantAuthentication['MerchantID'] = $this->getMerchantId();
        $data->PaymentMessage->MerchantAuthentication['Password'] = $this->getPassword();
        $data->PaymentMessage->TransactionDetails['Amount'] = $this->getAmountInteger();
        $data->PaymentMessage->TransactionDetails['CurrencyCode'] = $this->getCurrencyNumeric();
        $data->PaymentMessage->TransactionDetails->OrderID = $this->getTransactionId();
        $data->PaymentMessage->TransactionDetails->OrderDescription = $this->getDescription();
        $data->PaymentMessage->TransactionDetails->MessageDetails['TransactionType'] = $this->transactionType;
        $data->PaymentMessage->TransactionDetails->MessageDetails['NewTransaction'] = false;
        $data->PaymentMessage->TransactionDetails->MessageDetails['CrossReference'] = $this->getTransactionReference();

        // requires numeric country code
        // $data->PaymentMessage->CustomerDetails->BillingAddress->CountryCode = $this->getCard()->getCountryNumeric;
        $data->PaymentMessage->CustomerDetails->CustomerIPAddress = $this->getClientIp();

        return $data;
    }
}
