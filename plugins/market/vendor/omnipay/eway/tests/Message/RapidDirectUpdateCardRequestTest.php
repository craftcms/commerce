<?php

namespace Omnipay\Eway\Message;

use Omnipay\Tests\TestCase;

class RapidDirectUpdateCardRequestTest extends TestCase
{
    public function setUp()
    {
        $this->request = new RapidDirectUpdateCardRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize(array(
            'apiKey' => 'my api key',
            'password' => 'secret',
            'cardReference' => '987654321',
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
            'cardReference' => '987654321',
            'card' => array(
                'title' => 'Mr.',
                'firstName' => 'John',
                'lastName' => 'Smith',
                'billingAddress1' => 'Level 1',
                'billingAddress2' => '123 Test Lane',
                'billingState' => 'NSW',
                'billingCountry' => 'AU',
                'number' => '4111111111111111',
                'expiryMonth' => '12',
                'expiryYear' => gmdate('Y') + rand(1, 5),
                'cvv' => rand(100, 999),
            ),
        ));

        $data = $this->request->getData();
    
        $this->assertSame('UpdateTokenCustomer', $data['Method']);
        $this->assertSame('987654321', $data['Customer']['TokenCustomerID']);
        $this->assertSame(0, $data['Payment']['TotalAmount']);
        $this->assertSame('Mr.', $data['Customer']['Title']);
        $this->assertSame('John', $data['Customer']['FirstName']);
        $this->assertSame('Smith', $data['Customer']['LastName']);
        $this->assertSame('NSW', $data['Customer']['State']);
        $this->assertSame('au', $data['Customer']['Country']);
        $this->assertSame('4111111111111111', $data['Customer']['CardDetails']['Number']);
        $this->assertSame('12', $data['Customer']['CardDetails']['ExpiryMonth']);
    }
    
    public function testSendSuccess()
    {
        $this->setMockHttpResponse('RapidDirectUpdateCardRequestSuccess.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertSame(917758625852, $response->getCardReference());
        $this->assertSame('Transaction Approved', $response->getMessage());
    }

    public function testSendFailure()
    {
        $this->setMockHttpResponse('RapidDirectUpdateCardRequestFailure.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertSame(917758625852, $response->getCardReference());
        $this->assertSame('Invalid TotalAmount', $response->getMessage());
        $this->assertSame('V6011', $response->getCode());
    }
}
