<?php
/**
 * eWAY Rapid Refund Request
 */

namespace Omnipay\Eway\Message;

/**
 * eWAY Rapid Refund Request
 * 
 * Refund a transaction processed through eWAY. 
 * Requires the amount to refund and the eWAY Transaction ID of
 * the transaction to refund (passed as transactionReference).
 *
 * Example -- note this example assumes that the purchase has been successful
 * and that the transaction ID returned from the purchase is held in $txn_id.
 * See RapidDirectPurchaseRequest for the first part of this example transaction:
 *
 * <code>
 *   $transaction = $gateway->refund(array(
 *       'amount'    => '10.00',
 *       'currency'  => 'AUD',
 *   ));
 *   $transaction->setTransactionReference($txn_id);
 *   $response = $transaction->send();
 *   if ($response->isSuccessful()) {
 *       echo "Refund transaction was successful!\n";
 *       $data = $response->getData();
 *       echo "Gateway refund response data == " . print_r($data, true) . "\n";
 *   }
 * </code>
 *
 * @link https://eway.io/api-v3/#refunds
 * @see RapidDirectPurchaseRequest
 */
class RefundRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('transactionReference', 'amount');

        $data = $this->getBaseData();
        $data['Refund']['TotalAmount'] = $this->getAmountInteger();
        $data['Refund']['TransactionID'] = $this->getTransactionReference();
        $data['Refund']['InvoiceNumber'] = $this->getTransactionId();
        $data['Refund']['InvoiceDescription'] = $this->getDescription();
        $data['Refund']['CurrencyCode'] = $this->getCurrency();
        $data['Refund']['InvoiceReference'] = $this->getInvoiceReference();

        if ($this->getItems()) {
            $data['Items'] = $this->getItemData();
        }

        return $data;
    }

    public function sendData($data)
    {
        $httpResponse = $this->httpClient->post($this->getEndpoint(), null, json_encode($data))
            ->setAuth($this->getApiKey(), $this->getPassword())
            ->send();

        return $this->response = new RefundResponse($this, $httpResponse->json());
    }

    protected function getEndpoint()
    {
        return $this->getEndpointBase().'/DirectRefund.json';
    }
}
