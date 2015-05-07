<?php
/**
 * eWAY Rapid Direct Connection Gateway
 */

namespace Omnipay\Eway;

use Omnipay\Common\AbstractGateway;

/**
 * eWAY Rapid Direct Gateway
 *
 * This class forms the gateway class for eWAY Rapid Direct Connection requests.
 *
 * The eWAY Rapid gateways use an API Key and Password for authentication. 
 *
 * There is also a test sandbox environment, which uses a separate endpoint and 
 * API key and password. To access the eWAY Sandbox requires an eWAY Partner account.
 * https://myeway.force.com/success/partner-registration
 *
 * Simple Purchase Example:
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
 * @link https://eway.io/api-v3/#authentication
 * @link https://go.eway.io/s/article/How-do-I-setup-my-Live-eWAY-API-Key-and-Password
 */
class RapidDirectGateway extends AbstractGateway
{
    public $transparentRedirect = false;

    public function getName()
    {
        return 'eWAY Rapid Direct';
    }

    public function getDefaultParameters()
    {
        return array(
            'apiKey' => '',
            'password' => '',
            'testMode' => false,
        );
    }

    public function getApiKey()
    {
        return $this->getParameter('apiKey');
    }

    public function setApiKey($value)
    {
        return $this->setParameter('apiKey', $value);
    }

    public function getPassword()
    {
        return $this->getParameter('password');
    }

    public function setPassword($value)
    {
        return $this->setParameter('password', $value);
    }
    
    /**
     * Create a purchase request.
     *
     * Used for initiating a purchase transaction.
     * This resource accepts plain card details, an eWAY Token (as a cardReference)
     * or encrypted card details from eWAY's client side encryption.
     *
     * @link https://eway.io/api-v3/#direct-connection
     * @param array $parameters
     * @return \Omnipay\Eway\Message\RapidDirectPurchaseRequest
     */
    public function purchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\RapidDirectPurchaseRequest', $parameters);
    }

    /**
     * Create an authorisation request.
     *
     * To collect payment at a later time, a pre-auth can be made on a card.
     * You can then capture the payment to complete the sale and collect payment.
     *
     * Only available for Australian eWAY merchants
     *
     * @link https://eway.io/api-v3/#pre-auth
     * @param array $parameters
     * @return \Omnipay\Eway\Message\RapidDirectAuthorizeRequest
     */
    public function authorize(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\RapidDirectAuthorizeRequest', $parameters);
    }

    /**
     * Capture an authorisation.
     *
     * Use this resource to capture and process a previously created authorisation.
     * To use this resource requires the transaction reference from the authorisation.
     *
     * @link https://eway.io/api-v3/#capture-a-payment
     * @param array $parameters
     * @return \Omnipay\Eway\Message\RapidCaptureRequest
     */
    public function capture(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\RapidCaptureRequest', $parameters);
    }

    /**
     * Refund a Transaction
     *
     * Use this resource to refund a complete payment. To use this resource requires the transaction 
     * reference from the purchase or capture.
     *
     * @link https://eway.io/api-v3/#refunds
     * @param array $parameters
     * @return \Omnipay\Eway\Message\RefundRequest
     */
    public function refund(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\RefundRequest', $parameters);
    }

    /**
     * Store a credit card as a Token
     *
     * You can currently securely store card details with eWAY for future
     * charging using eWAY's Tokens.
     * After storing the card, pass the cardReference instead of the card
     * details to complete a payment.
     * 
     * @link https://eway.io/api-v3/#create-token-customer
     * @param array $parameters
     * @return \Omnipay\Eway\Message\RapidDirectCreateCardRequest
     */
    public function createCard(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\RapidDirectCreateCardRequest', $parameters);
    }

    /**
     * Update a credit card stored as a Token
     *
     * You can currently securely store card details with eWAY for future
     * charging using eWAY's Tokens.
     * This resource requires the cardReference for the card to be updated.
     * 
     * @link https://eway.io/api-v3/#update-token-customer
     * @param array $parameters
     * @return \Omnipay\Eway\Message\RapidDirectUpdateCardRequest
     */
    public function updateCard(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Eway\Message\RapidDirectUpdateCardRequest', $parameters);
    }
}
