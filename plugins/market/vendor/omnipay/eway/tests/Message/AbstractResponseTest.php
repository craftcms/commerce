<?php

namespace Omnipay\Eway\Message;

use Mockery as m;
use Omnipay\Tests\TestCase;

class AbstractResponseTest extends TestCase
{

    public function setUp()
    {
        $this->response = m::mock('\Omnipay\Eway\Message\AbstractResponse')->makePartial();
    }

    public function testIsSuccessful()
    {
        $data = array('TransactionStatus' => true);
        $request = $this->getMockRequest();
        $this->response = m::mock('\Omnipay\Eway\Message\AbstractResponse', array($request, $data))->makePartial();

        $this->assertTrue($this->response->isSuccessful());
    }

    public function testGetMessage()
    {
        $data = array('ResponseMessage' => 'A2000');
        $request = $this->getMockRequest();
        $this->response = m::mock('\Omnipay\Eway\Message\AbstractResponse', array($request, $data))->makePartial();

        $this->assertSame('Transaction Approved', $this->response->getMessage());
    }

    public function testGetMessageMultiple()
    {
        $data = array('ResponseMessage' => 'V6101,V6102');
        $request = $this->getMockRequest();
        $this->response = m::mock('\Omnipay\Eway\Message\AbstractResponse', array($request, $data))->makePartial();

        $this->assertSame('Invalid EWAY_CARDEXPIRYMONTH, Invalid EWAY_CARDEXPIRYYEAR', $this->response->getMessage());
    }
}
