<?php

namespace Omnipay\Eway;

use Omnipay\Tests\GatewayTestCase;

class RapidDirectGatewayTest extends GatewayTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->gateway = new RapidDirectGateway($this->getHttpClient(), $this->getHttpRequest());
    }

    public function testPurchase()
    {
        $request = $this->gateway->purchase(array('amount' => '10.00'));

        $this->assertInstanceOf('Omnipay\Eway\Message\RapidDirectPurchaseRequest', $request);
        $this->assertSame('10.00', $request->getAmount());
    }

    public function testAuthorise()
    {
        $request = $this->gateway->authorize(array('amount' => '10.00'));

        $this->assertInstanceOf('Omnipay\Eway\Message\RapidDirectAuthorizeRequest', $request);
        $this->assertSame('10.00', $request->getAmount());
    }

    public function testCapture()
    {
        $request = $this->gateway->capture(array('amount' => '10.00', 'transactionId' => '87654321'));

        $this->assertInstanceOf('Omnipay\Eway\Message\RapidCaptureRequest', $request);
        $this->assertSame('10.00', $request->getAmount());
    }

    public function testCreateCard()
    {
        $request = $this->gateway->createCard(array('amount' => '10.00'));

        $this->assertInstanceOf('Omnipay\Eway\Message\RapidDirectCreateCardRequest', $request);
        $this->assertSame('10.00', $request->getAmount());
    }
}
