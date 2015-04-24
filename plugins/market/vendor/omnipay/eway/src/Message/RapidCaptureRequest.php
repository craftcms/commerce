<?php
/**
 * eWAY Rapid Capture Request
 */
 
namespace Omnipay\Eway\Message;

/**
 * eWAY Rapid Capture Request
 *
 * This is a request to capture and process a previously created authorisation.
 *
 * Example - note this example assumes that the authorisation has been successful
 *  and that the Transaction ID returned from the authorisation is held in $txn_id.
 *  See RapidDirectAuthorizeRequest for the first part of this example.
 *
 * <code>
 *   // Once the transaction has been authorized, we can capture it for final payment.
 *   $transaction = $gateway->capture(array(
 *       'amount'        => '10.00',
 *       'currency'      => 'AUD',
 *   ));
 *   $transaction->setTransactionReference($txn_id);
 *   $response = $transaction->send();
 * </code>
 *
 * @link https://eway.io/api-v3/#pre-auth
 * @see RapidDirectAuthorizeRequest
 */
class RapidCaptureRequest extends AbstractRequest
{
    public function getData()
    {
        $this->validate('amount', 'transactionReference');

        $data = array();

        $data['Payment'] = array();
        $data['Payment']['TotalAmount'] = $this->getAmountInteger();
        $data['Payment']['InvoiceNumber'] = $this->getTransactionId();
        $data['Payment']['InvoiceDescription'] = $this->getDescription();
        $data['Payment']['CurrencyCode'] = $this->getCurrency();
        $data['Payment']['InvoiceReference'] = $this->getInvoiceReference();

        $data['TransactionId'] = $this->getTransactionReference();

        return $data;
    }

    public function getEndpoint()
    {
        return $this->getEndpointBase().'/CapturePayment';
    }
    
    public function sendData($data)
    {
        // This request uses the REST endpoint and requires the JSON content type header
        $httpResponse = $this->httpClient->post(
            $this->getEndpoint(),
            array('content-type' => 'application/json'),
            json_encode($data)
        )
        ->setAuth($this->getApiKey(), $this->getPassword())
        ->send();

        return $this->response = new RapidResponse($this, $httpResponse->json());
    }
}
