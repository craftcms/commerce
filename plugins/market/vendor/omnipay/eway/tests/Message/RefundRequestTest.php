<?php

namespace Omnipay\Eway\Message;

use Omnipay\Tests\TestCase;

class RapidRefundRequestTest extends TestCase
{
    public function setUp()
    {
        $this->request = new RefundRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize(array(
            'apiKey' => 'my api key',
            'password' => 'secret',
            'amount' => '10.00',
            'transactionReference' => '87654321',
        ));
    }

    public function testGetData()
    {
        $this->request->initialize(array(
            'apiKey' => 'my api key',
            'password' => 'secret',
            'partnerId' => '1234',
            'amount' => '10.00',
            'transactionReference' => '87654321',
            'transactionId' => '999',
            'description' => 'new car',
            'currency' => 'AUD',
            'invoiceReference' => 'INV-123',
            'clientIp' => '127.0.0.1',
            'card' => array(
                'firstName' => 'Patrick',
                'lastName' => 'Collison',
                'shippingFirstName' => 'John',
                'shippingLastName' => 'Smith',
                'shippingAddress1' => 'Level 1',
                'shippingAddress2' => '123 Test Lane',
                'shippingState' => 'NSW',
                'shippingCountry' => 'AU',
            ),
        ));

        $data = $this->request->getData();

        $this->assertSame('127.0.0.1', $data['CustomerIP']);
        $this->assertSame('1234', $data['PartnerID']);
        $this->assertSame(1000, $data['Refund']['TotalAmount']);
        $this->assertSame('87654321', $data['Refund']['TransactionID']);
        $this->assertSame('999', $data['Refund']['InvoiceNumber']);
        $this->assertSame('new car', $data['Refund']['InvoiceDescription']);
        $this->assertSame('INV-123', $data['Refund']['InvoiceReference']);
        $this->assertSame('AUD', $data['Refund']['CurrencyCode']);
        $this->assertSame('Patrick', $data['Customer']['FirstName']);
        $this->assertSame('Collison', $data['Customer']['LastName']);
        $this->assertSame('John', $data['ShippingAddress']['FirstName']);
        $this->assertSame('Smith', $data['ShippingAddress']['LastName']);
        $this->assertSame('NSW', $data['ShippingAddress']['State']);
        $this->assertSame('au', $data['ShippingAddress']['Country']);
    }

    public function testGetDataWithItems()
    {
        $this->request->initialize(array(
            'apiKey' => 'my api key',
            'password' => 'secret',
            'amount' => '10.00',
            'transactionReference' => '87654321',
            'transactionId' => '999',
            'description' => 'new car',
            'currency' => 'AUD',
            'clientIp' => '127.0.0.1',
            'card' => array(
                'firstName' => 'Patrick',
                'lastName' => 'Collison',
            ),
        ));

        $this->request->setItems(array(
            array('name' => 'Floppy Disk', 'description' => 'MS-DOS', 'quantity' => 2, 'price' => 10),
            array('name' => 'CD-ROM', 'description' => 'Windows 95', 'quantity' => 1, 'price' => 40),
        ));

        $data = $this->request->getData();

        $this->assertSame('Floppy Disk', $data['Items'][0]['SKU']);
        $this->assertSame('MS-DOS', $data['Items'][0]['Description']);
        $this->assertSame('2', $data['Items'][0]['Quantity']);
        $this->assertSame('1000', $data['Items'][0]['UnitCost']);

        $this->assertSame('CD-ROM', $data['Items'][1]['SKU']);
        $this->assertSame('Windows 95', $data['Items'][1]['Description']);
        $this->assertSame('1', $data['Items'][1]['Quantity']);
        $this->assertSame('4000', $data['Items'][1]['UnitCost']);
    }

    public function testSendSuccess()
    {
        $this->setMockHttpResponse('RapidRefundRequestSuccess.txt');
        $response = $this->request->send();

        $this->assertTrue($response->isSuccessful());
        $this->assertSame('11092404', $response->getTransactionReference());
        $this->assertSame('A2000', $response->getCode());
    }

    public function testSendFailure()
    {
        $this->setMockHttpResponse('RapidRefundRequestFailure.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertNull($response->getTransactionReference());
        $this->assertSame('Unauthorised API Access, Account Not PCI Certified, Invalid Refund Transaction ID', $response->getMessage());
        $this->assertSame('V6111,V6115', $response->getCode());
    }
}