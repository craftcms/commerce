<?php

namespace Omnipay\Eway;

use Omnipay\Tests\GatewayTestCase;

class RapidSharedGatewayTest extends GatewayTestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->gateway = new RapidSharedGateway($this->getHttpClient(), $this->getHttpRequest());
    }

    public function testPurchase()
    {
        $request = $this->gateway->purchase(array('amount' => '10.00'));

        $this->assertInstanceOf('Omnipay\Eway\Message\RapidSharedPurchaseRequest', $request);
        $this->assertSame('10.00', $request->getAmount());
    }

    public function testPurchaseReturn()
    {
        $request = $this->gateway->completePurchase(array('amount' => '10.00'));

        $this->assertInstanceOf('Omnipay\Eway\Message\RapidCompletePurchaseRequest', $request);
        $this->assertSame('10.00', $request->getAmount());
    }
}
