<?php

namespace Omnipay\Eway\Message;

use Omnipay\Tests\TestCase;

class RapidDirectCreateCardRequestTest extends TestCase
{
    public function setUp()
    {
        $this->request = new RapidDirectCreateCardRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize(array(
            'apiKey' => 'my api key',
            'password' => 'secret',
            'card' => array(
                'title' => 'Mr.',
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
            'shippingMethod' => 'NextDay',
            'transactionId' => '999',
            'description' => 'new car',
            'currency' => 'AUD',
            'invoiceReference' => 'INV-123',
            'clientIp' => '127.0.0.1',
            'card' => array(
                'title' => 'Mr.',
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
            ),
        ));

        $data = $this->request->getData();

        $this->assertSame('127.0.0.1', $data['CustomerIP']);
        $this->assertSame('1234', $data['PartnerID']);
        $this->assertSame('NextDay', $data['ShippingMethod']);
        $this->assertSame(0, $data['Payment']['TotalAmount']);
        $this->assertSame('Mr.', $data['Customer']['Title']);
        $this->assertSame('John', $data['Customer']['FirstName']);
        $this->assertSame('Smith', $data['Customer']['LastName']);
        $this->assertSame('Bob', $data['ShippingAddress']['FirstName']);
        $this->assertSame('Mann', $data['ShippingAddress']['LastName']);
        $this->assertSame('NSW', $data['ShippingAddress']['State']);
        $this->assertSame('au', $data['ShippingAddress']['Country']);
        $this->assertSame('4111111111111111', $data['Customer']['CardDetails']['Number']);
        $this->assertSame('12', $data['Customer']['CardDetails']['ExpiryMonth']);
    }
    
    public function testSendSuccess()
    {
        $this->setMockHttpResponse('RapidDirectCreateCardRequestSuccess.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame(916260137222, $response->getCardReference());
        $this->assertSame('Transaction Approved', $response->getMessage());
    }

    public function testSendFailure()
    {
        $this->setMockHttpResponse('RapidDirectCreateCardRequestFailure.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertNull($response->getCardReference());
        $this->assertSame('Customer First Name Required', $response->getMessage());
        $this->assertSame('V6042', $response->getCode());
    }
}
