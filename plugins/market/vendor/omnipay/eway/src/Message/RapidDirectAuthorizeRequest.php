<?php
/**
 * eWAY Rapid Direct Authorise Request
 */
 
namespace Omnipay\Eway\Message;

/**
 * eWAY Rapid Direct Authorise Request
 *
 * Completes an authorise transaction (pre-auth) using eWAY's Rapid Direct 
 * Connection API. This looks exactly like a RapidDirectPurchaseRequest,
 * except the Method is set to "Authorise" (note British English spelling).
 * 
 * The returned transaction ID (use getTransactionReference()) can be used to
 * capture the payment.
 *
 * Note that Authorisation is only available for Australian merchants with eWAY.
 *
 * Example:
 *
 * <code>
 *   // Create a gateway for the eWAY Direct Gateway
 *   $gateway = Omnipay::create('Eway_RapidDirect');
 *
 *   // Initialise the gateway
 *   $gateway->initialize(array(
 *      'apiKey' => 'Rapid API Key',
 *      'password' => 'Rapid API Password',
 *      'testMode' => true, // Or false when you are ready for live transactions
 *   ));
 *
 *   // Create a credit card object
 *   $card = new CreditCard(array(
 *             'firstName'          => 'Example',
 *             'lastName'           => 'User',
 *             'number'             => '4444333322221111',
 *             'expiryMonth'        => '01',
 *             'expiryYear'         => '2020',
 *             'cvv'                => '321',
 *             'billingAddress1'    => '1 Scrubby Creek Road',
 *             'billingCountry'     => 'AU',
 *             'billingCity'        => 'Scrubby Creek',
 *             'billingPostcode'    => '4999',
 *             'billingState'       => 'QLD',
 *   ));
 *
 *   // Do an authorisation transaction on the gateway
 *   $request = $gateway->authorize(array(
 *      'amount'            => '10.00',
 *      'currency'          => 'AUD',
 *      'transactionType'   => 'Purchase',
 *      'card'              => $card,
 *   ));
 *
 *   $response = $request->send();
 *   if ($response->isSuccessful()) {
 *       echo "Authorisation transaction was successful!\n";
 *       $txn_id = $response->getTransactionReference();
 *       echo "Transaction ID = " . $txn_id . "\n";
 *   }
 * </code>
 *
 * @link https://eway.io/api-v3/#direct-connection
 * @link https://eway.io/api-v3/#pre-auth
 * @see RapidCaptureRequest
 */
class RapidDirectAuthorizeRequest extends RapidDirectAbstractRequest
{
    public function getData()
    {
        $data = $this->getBaseData();
        
        $this->validate('amount');

        $data['Payment'] = array();
        $data['Payment']['TotalAmount'] = $this->getAmountInteger();
        $data['Payment']['InvoiceNumber'] = $this->getTransactionId();
        $data['Payment']['InvoiceDescription'] = $this->getDescription();
        $data['Payment']['CurrencyCode'] = $this->getCurrency();
        $data['Payment']['InvoiceReference'] = $this->getInvoiceReference();
        
        $data['Method'] = 'Authorise';
        
        return $data;
    }

    protected function getEndpoint()
    {
        return $this->getEndpointBase().'/DirectPayment.json';
    }
}
