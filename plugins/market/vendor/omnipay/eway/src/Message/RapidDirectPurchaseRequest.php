<?php
/**
 * eWAY Rapid Direct Purchase Request
 */
 
namespace Omnipay\Eway\Message;

/**
 * eWAY Rapid Direct Purchase Request
 *
 * Completes a transaction using eWAY's Rapid Direct Connection API.
 * This request can process a purchase with card details or with an
 * eWAY Token (passed as the cardReference).
 *
 * Using Direct Connection to pass card details in the clear requires
 * proof of PCI compliance to eWAY. Alternatively they can be 
 * encrypted using Client Side Encryption - in which case the card 
 * number and CVN should be passed using the encryptedCardNumber and
 * encryptedCardCvv respectively (these are not in the CreditCard
 * object).
 *
 * Simple Example:
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
 *   // Do a purchase transaction on the gateway
 *   $request = $gateway->purchase(array(
 *      'amount'            => '10.00',
 *      'currency'          => 'AUD',
 *      'transactionType'   => 'Purchase',
 *      'card'              => $card,
 *   ));
 *
 *   $response = $request->send();
 *   if ($response->isSuccessful()) {
 *       echo "Purchase transaction was successful!\n";
 *       $txn_id = $response->getTransactionReference();
 *       echo "Transaction ID = " . $txn_id . "\n";
 *   }
 * </code>
 *
 * @link https://eway.io/api-v3/#direct-connection
 * @link https://eway.io/api-v3/#client-side-encryption
 * @see RapidDirectAbstractRequest
 */
class RapidDirectPurchaseRequest extends RapidDirectAbstractRequest
{
    public function getData()
    {
        $data = $this->getBaseData();
        
        $this->validate('amount', 'transactionType');

        $data['Payment'] = array();
        $data['Payment']['TotalAmount'] = $this->getAmountInteger();
        $data['Payment']['InvoiceNumber'] = $this->getTransactionId();
        $data['Payment']['InvoiceDescription'] = $this->getDescription();
        $data['Payment']['CurrencyCode'] = $this->getCurrency();
        $data['Payment']['InvoiceReference'] = $this->getInvoiceReference();
        
        if ($this->getCardReference()) {
            $data['Method'] = 'TokenPayment';
        } else {
            $data['Method'] = 'ProcessPayment';
        }
        
        return $data;
    }
    
    /**
     * Get transaction endpoint.
     *
     * @return string
     */
    protected function getEndpoint()
    {
        return $this->getEndpointBase().'/DirectPayment.json';
    }
}
