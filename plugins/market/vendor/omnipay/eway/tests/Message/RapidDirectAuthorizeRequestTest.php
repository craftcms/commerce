<?php

namespace Omnipay\Eway\Message;

use Omnipay\Tests\TestCase;

class RapidDirectAuthorizeRequestTest extends TestCase
{
    public function setUp()
    {
        $this->request = new RapidDirectAuthorizeRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize(array(
            'apiKey' => 'my api key',
            'password' => 'secret',
            'amount' => '10.00',
            'card' => array(
                'firstName' => 'John',
                'lastName' => 'Smith',
                'number' => '4111111111111111',
                'expiryMonth' => '12',
                'expiryYear' => gmdate('Y') + rand(1, 5),
                'cvv' => rand(100, 999),
            ),
        ));
    }

    public function testGetData()
    {
        $this->request->initialize(array(
            'apiKey' => 'my api key',
            'password' => 'secret',
            'partnerId' => '1234',
            'transactionType' => 'Purchase',
            'shippingMethod' => 'NextDay',
            'amount' => '10.00',
            'transactionId' => '999',
            'description' => 'new car',
            'currency' => 'AUD',
            'invoiceReference' => 'INV-123',
            'clientIp' => '127.0.0.1',
            'card' => array(
                'firstName' => 'John',
                'lastName' => 'Smith',
                'shippingFirstName' => 'Bob',
                'shippingLastName' => 'Mann',
                'shippingAddress1' => 'Level 1',
                'shippingAddress2' => '123 Test Lane',
                'shippingState' => 'NSW',
                'shippingCountry' => 'AU',
                'number' => '4111111111111111',
                'expiryMonth' => '12',
                'expiryYear' => gmdate('Y') + rand(1, 5),
                'cvv' => rand(100, 999),
                'startMonth' => '01',
                'startYear' => '13',
                'issueNumber' => '1',
            ),
        ));

        $data = $this->request->getData();
    
        $this->assertSame('Authorise', $data['Method']);
        $this->assertSame('127.0.0.1', $data['CustomerIP']);
        $this->assertSame('1234', $data['PartnerID']);
        $this->assertSame('Purchase', $data['TransactionType']);
        $this->assertSame('NextDay', $data['ShippingMethod']);
        $this->assertSame(1000, $data['Payment']['TotalAmount']);
        $this->assertSame('999', $data['Payment']['InvoiceNumber']);
        $this->assertSame('new car', $data['Payment']['InvoiceDescription']);
        $this->assertSame('INV-123', $data['Payment']['InvoiceReference']);
        $this->assertSame('AUD', $data['Payment']['CurrencyCode']);
        $this->assertSame('John', $data['Customer']['FirstName']);
        $this->assertSame('Smith', $data['Customer']['LastName']);
        $this->assertSame('Bob', $data['ShippingAddress']['FirstName']);
        $this->assertSame('Mann', $data['ShippingAddress']['LastName']);
        $this->assertSame('NSW', $data['ShippingAddress']['State']);
        $this->assertSame('au', $data['ShippingAddress']['Country']);
        $this->assertSame('4111111111111111', $data['Customer']['CardDetails']['Number']);
        $this->assertSame('12', $data['Customer']['CardDetails']['ExpiryMonth']);
        $this->assertSame('01', $data['Customer']['CardDetails']['StartMonth']);
        $this->assertSame('13', $data['Customer']['CardDetails']['StartYear']);
        $this->assertSame('1', $data['Customer']['CardDetails']['IssueNumber']);
    }

    public function testSendSuccess()
    {
        $this->setMockHttpResponse('RapidDirectAuthoriseRequestSuccess.txt');
        $response = $this->request->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertSame('11369246', $response->getTransactionReference());
        $this->assertSame('Transaction Approved', $response->getMessage());
    }

    public function testSendFailure()
    {
        $this->setMockHttpResponse('RapidDirectAuthoriseRequestFailure.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertNull($response->getTransactionReference());
        $this->assertSame('Invalid TotalAmount', $response->getMessage());
        $this->assertSame('V6011', $response->getCode());
    }
}
