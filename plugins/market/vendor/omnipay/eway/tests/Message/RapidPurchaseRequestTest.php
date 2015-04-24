<?php

namespace Omnipay\Eway\Message;

use Omnipay\Tests\TestCase;

class RapidPurchaseRequestTest extends TestCase
{
    public function setUp()
    {
        $this->request = new RapidPurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
        $this->request->initialize(array(
            'apiKey' => 'my api key',
            'password' => 'secret',
            'amount' => '10.00',
            'returnUrl' => 'https://www.example.com/return',
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
            'returnUrl' => 'https://www.example.com/return',
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
        $this->assertSame('Purchase', $data['TransactionType']);
        $this->assertSame('NextDay', $data['ShippingMethod']);
        $this->assertSame('https://www.example.com/return', $data['RedirectUrl']);
        $this->assertSame(1000, $data['Payment']['TotalAmount']);
        $this->assertSame('999', $data['Payment']['InvoiceNumber']);
        $this->assertSame('new car', $data['Payment']['InvoiceDescription']);
        $this->assertSame('INV-123', $data['Payment']['InvoiceReference']);
        $this->assertSame('AUD', $data['Payment']['CurrencyCode']);
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
            'transactionId' => '999',
            'description' => 'new car',
            'currency' => 'AUD',
            'clientIp' => '127.0.0.1',
            'returnUrl' => 'https://www.example.com/return',
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
        $this->setMockHttpResponse('RapidPurchaseRequestSuccess.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isRedirect());
        $this->assertSame('POST', $response->getRedirectMethod());
        $this->assertSame('https://secure-au.sandbox.ewaypayments.com/Process', $response->getRedirectUrl());
        $this->assertSame(array('EWAY_ACCESSCODE' => 'F9802j0-O7sdVLnOcb_3IPryTxHDtKY8u_0pb10GbYq-Xjvbc-5Bc_LhI-oBIrTxTCjhOFn7Mq-CwpkLDja5-iu-Dr3DjVTr9u4yxSB5BckdbJqSA4WWydzDO0jnPWfBdKcWL'), $response->getRedirectData());
        $this->assertNull($response->getTransactionReference());
        $this->assertNull($response->getMessage());
        $this->assertNull($response->getCode());
    }

    public function testSendFailure()
    {
        $this->setMockHttpResponse('RapidPurchaseRequestFailure.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertNull($response->getRedirectUrl());
        $this->assertNull($response->getRedirectData());
        $this->assertNull($response->getTransactionReference());
        $this->assertSame('Invalid TotalAmount', $response->getMessage());
        $this->assertSame('V6011', $response->getCode());
    }
}
