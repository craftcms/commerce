<?php

namespace Omnipay\Eway\Message;

use Omnipay\Tests\TestCase;

class RapidCaptureRequestTest extends TestCase
{
    public function setUp()
    {
        $this->request = new RapidCaptureRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize(array(
            'apiKey' => 'my api key',
            'password' => 'secret',
            'amount' => '10.00',
            'transactionReference' => '12345678',
        ));
    }

    public function testGetData()
    {
        $this->request->initialize(array(
            'apiKey' => 'my api key',
            'password' => 'secret',
            'amount' => '10.00',
            'currency' => 'AUD',
            'transactionReference' => '12345678',
            'description' => 'new car',
            'transactionId' => '999',
            'invoiceReference' => 'INV-123',
        ));

        $data = $this->request->getData();

        $this->assertSame(1000, $data['Payment']['TotalAmount']);
        $this->assertSame('999', $data['Payment']['InvoiceNumber']);
        $this->assertSame('new car', $data['Payment']['InvoiceDescription']);
        $this->assertSame('INV-123', $data['Payment']['InvoiceReference']);
        $this->assertSame('AUD', $data['Payment']['CurrencyCode']);
        $this->assertSame('12345678', $data['TransactionId']);
    }

    public function testSendSuccess()
    {
        $this->setMockHttpResponse('RapidCaptureRequestSuccess.txt');
        $response = $this->request->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertSame('11369052', $response->getTransactionReference());
    }

    public function testSendFailure()
    {
        $this->setMockHttpResponse('RapidCaptureRequestFailure.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertSame('0', $response->getTransactionReference());
        $this->assertSame('Error', $response->getMessage());
        $this->assertSame('D4406', $response->getCode());
    }
}
