<?php

namespace Omnipay\Eway;

use Omnipay\Tests\GatewayTestCase;
use Omnipay\Common\CreditCard;

class DirectGatewayTest extends GatewayTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->gateway = new DirectGateway($this->getHttpClient(), $this->getHttpRequest());

        $this->gateway->setCustomerId('999999999');
        $this->gateway->setTestMode(true);

        $card = new CreditCard($this->getValidCard());

        $this->purchaseOptions = array(
            'amount' => '10.00',
            'card' => $card
        );

        $this->captureOptions = array(
            'amount' => '10.00',
            'transactionId' => '10451614'
        );

        $this->refundOptions = array(
        	'card' 				=> $card,
            'amount' 			=> '10.00',
            'transactionId' 	=> '10451628',
            'refundPassword' 	=> 'Refund123'
        );

        $this->voidOptions = array(
            'transactionId' 	=> '10451636'
        );
    }

    public function testAuthorizeSuccess()
    {
    	$this->setMockHttpResponse('DirectAuthorizeSuccess.txt');

        $request = $this->gateway->authorize($this->purchaseOptions);

        $response = $request->send();

        $this->assertInstanceOf('Omnipay\Eway\Message\DirectAuthorizeRequest', $request);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('10.00', $request->getAmount());
        $this->assertSame(10451615, $response->getTransactionReference());
        $this->assertSame('00, Transaction Approved (Sandbox)', $response->getMessage());
    }

    public function testAuthorizeFailure()
    {
    	$this->purchaseOptions['amount'] = '10.63';

    	$this->setMockHttpResponse('DirectAuthorizeFailure.txt');

        $request = $this->gateway->authorize($this->purchaseOptions);

        $response = $request->send();

        $this->assertInstanceOf('Omnipay\Eway\Message\DirectAuthorizeRequest', $request);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('10.63', $request->getAmount());
        $this->assertSame(10451614, $response->getTransactionReference());
        $this->assertSame('63, Security Violation (Sandbox)', $response->getMessage());
    }

    public function testCaptureSuccess()
    {
    	$this->setMockHttpResponse('DirectCaptureSuccess.txt');

        $request = $this->gateway->capture($this->captureOptions);

        $response = $request->send();

        $this->assertInstanceOf('Omnipay\Eway\Message\DirectCaptureRequest', $request);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('10.00', $request->getAmount());
        $this->assertSame(10451626, $response->getTransactionReference());
        $this->assertSame('00, Transaction Approved (Sandbox)', $response->getMessage());
    }

    public function testCaptureFailure()
    {
    	$this->setMockHttpResponse('DirectCaptureFailure.txt');

        $request = $this->gateway->capture($this->captureOptions);

        $response = $request->send();

        $this->assertInstanceOf('Omnipay\Eway\Message\DirectCaptureRequest', $request);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('10.00', $request->getAmount());
        $this->assertSame('Error: This authorisation has already been completed. Your transaction could not be processed.', $response->getMessage());
    }

    public function testPurchaseSuccess()
    {
    	$this->setMockHttpResponse('DirectPurchaseSuccess.txt');

        $request = $this->gateway->purchase($this->purchaseOptions);

        $response = $request->send();

        $this->assertInstanceOf('Omnipay\Eway\Message\DirectPurchaseRequest', $request);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('10.00', $request->getAmount());
        $this->assertSame(10451628, $response->getTransactionReference());
        $this->assertSame('00, Transaction Approved (Sandbox)', $response->getMessage());
    }

    public function testPurchaseFailure()
    {
    	$this->purchaseOptions['amount'] = '10.63';

    	$this->setMockHttpResponse('DirectPurchaseFailure.txt');

        $request = $this->gateway->purchase($this->purchaseOptions);

        $response = $request->send();

        $this->assertInstanceOf('Omnipay\Eway\Message\DirectPurchaseRequest', $request);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('10.63', $request->getAmount());
        $this->assertSame(10451629, $response->getTransactionReference());
        $this->assertSame('63, Security Violation (Sandbox)', $response->getMessage());
    }

    public function testRefundSuccess()
    {
    	$this->setMockHttpResponse('DirectRefundSuccess.txt');

        $request = $this->gateway->refund($this->refundOptions);

        $response = $request->send();

        $this->assertInstanceOf('Omnipay\Eway\Message\DirectRefundRequest', $request);

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('10.00', $request->getAmount());
        $this->assertSame(10451632, $response->getTransactionReference());
        $this->assertSame('00,Transaction Approved (Sandbox)', $response->getMessage());
    }

    public function testRefundFailure()
    {
    	$this->setMockHttpResponse('DirectRefundFailure.txt');

        $request = $this->gateway->refund($this->refundOptions);

        $response = $request->send();

        $this->assertInstanceOf('Omnipay\Eway\Message\DirectRefundRequest', $request);

        $this->assertFalse($response->isSuccessful());
        $this->assertSame('10.00', $request->getAmount());
        $this->assertSame('Error: This transaction has already been refunded for its total amount. Your refund could not be processed.', $response->getMessage());
    }

    public function testVoidSuccess()
    {
    	$this->setMockHttpResponse('DirectVoidSuccess.txt');

        $request = $this->gateway->void($this->voidOptions);

        $response = $request->send();

        $this->assertInstanceOf('Omnipay\Eway\Message\DirectVoidRequest', $request);

        $this->assertTrue($response->isSuccessful());
        $this->assertNull($request->getAmount());
        $this->assertSame(10451638, $response->getTransactionReference());
        $this->assertSame('00, Transaction Approved (Sandbox)', $response->getMessage());
    }

    public function testVoidFailure()
    {
    	$this->setMockHttpResponse('DirectVoidFailure.txt');

        $request = $this->gateway->void($this->voidOptions);

        $response = $request->send();

        $this->assertInstanceOf('Omnipay\Eway\Message\DirectVoidRequest', $request);

        $this->assertFalse($response->isSuccessful());
        $this->assertNull($request->getAmount());
        $this->assertSame('Error: This authorisation has already been voided. Your transaction could not be processed.', $response->getMessage());
    }

}
