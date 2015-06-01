<?php
/**
 * eWAY Rapid Direct Update Card Request
 */

namespace Omnipay\Eway\Message;

/**
 * eWAY Rapid Direct Update Card Request
 *
 * Update card data stored as a token with eWAY using eWAY's Rapid 
 * Direct Connection API. 
 *
 * This requires the TokenCustomerID of the token being updated, handled
 * in OmniPay as the cardReference.
 *
 * Note that since no transaction is processed, the transaction
 * status is returned as false when a token is created. This means that
 * isSuccessful() cannot be used to check for success.
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
 *             'number'             => '5454545454545454',
 *             'expiryMonth'        => '01',
 *             'expiryYear'         => '2022',
 *             'billingAddress1'    => '2 Scrubby Creek Road',
 *             'billingCountry'     => 'AU',
 *             'billingCity'        => 'Scrubby Creek',
 *             'billingPostcode'    => '4998',
 *             'billingState'       => 'QLD',
 *   ));
 *
 *   // Do a create card transaction on the gateway
 *   $request = $gateway->updateCard(array(
 *      'card'              => $card,
 *      'cardReference'     => $cardReference,
 *   ));
 *
 *   $response = $request->send();
 *   $cardReference = $response->getCardReference();
 * </code>
 *
 * @link https://eway.io/api-v3/#direct-connection
 * @link https://eway.io/api-v3/#token-payments
 */
class RapidDirectUpdateCardRequest extends RapidDirectAbstractRequest
{
    public function getData()
    {
        $data = $this->getBaseData();
        
        $this->validate('cardReference');
        
        $data['Payment'] = array();
        $data['Payment']['TotalAmount'] = 0;
        
        $data['Customer']['TokenCustomerID'] = $this->getCardReference();
        
        $data['Method'] = 'UpdateTokenCustomer';
        
        return $data;
    }

    protected function getEndpoint()
    {
        return $this->getEndpointBase().'/DirectPayment.json';
    }
}
