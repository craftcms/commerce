<?php

namespace Omnipay\Eway\Message;

use Omnipay\Tests\TestCase;

class RapidSharedPurchaseRequestTest extends TestCase
{
    public function setUp()
    {
        $this->request = new RapidSharedPurchaseRequest($this->getHttpClient(), $this->getHttpRequest());
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
        $this->setMockHttpResponse('RapidSharedPurchaseRequestSuccess.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isRedirect());
        $this->assertSame('GET', $response->getRedirectMethod());
        $this->assertSame('https://secure.ewaypayments.com/sharedpayment?AccessCode=F9802j0-O7sdVLnOcb_3IPryTxHDtKY8u_0pb10GbYq-Xjvbc-5Bc_LhI-oBIrTxTCjhOFn7Mq-CwpkLDja5-iu-Dr3DjVTr9u4yxSB5BckdbJqSA4WWydzDO0jnPWfBdKcWL', $response->getRedirectUrl());
        $this->assertNull($response->getRedirectData());
        $this->assertNull($response->getTransactionReference());
        $this->assertNull($response->getCardReference());
        $this->assertNull($response->getMessage());
        $this->assertNull($response->getCode());
    }

    public function testSendFailure()
    {
        $this->setMockHttpResponse('RapidSharedPurchaseRequestFailure.txt');
        $response = $this->request->send();

        $this->assertFalse($response->isSuccessful());
        $this->assertFalse($response->isRedirect());
        $this->assertNull($response->getRedirectUrl());
        $this->assertNull($response->getRedirectData());
        $this->assertNull($response->getCardReference());
        $this->assertNull($response->getTransactionReference());
        $this->assertSame('Invalid TotalAmount', $response->getMessage());
        $this->assertSame('V6011', $response->getCode());
    }

    public function testCancelUrl()
    {
        $this->assertSame($this->request, $this->request->setCancelUrl('http://www.example.com'));
        $this->assertSame('http://www.example.com', $this->request->getCancelUrl());
    }

    public function testLogoUrl()
    {
        $this->assertSame($this->request, $this->request->setLogoUrl('https://www.example.com/logo.jpg'));
        $this->assertSame('https://www.example.com/logo.jpg', $this->request->getLogoUrl());
    }

    public function testHeaderText()
    {
        $this->assertSame($this->request, $this->request->setHeaderText('Header Text'));
        $this->assertSame('Header Text', $this->request->getHeaderText());
    }

    public function testLanguage()
    {
        $this->assertSame($this->request, $this->request->setLanguage('EN'));
        $this->assertSame('EN', $this->request->getLanguage());
    }

    public function testCustomerReadOnly()
    {
        $this->assertSame($this->request, $this->request->setCustomerReadOnly('true'));
        $this->assertSame('true', $this->request->getCustomerReadOnly());
    }

    public function testCustomView()
    {
        $this->assertSame($this->request, $this->request->setCustomView('Bootstrap'));
        $this->assertSame('Bootstrap', $this->request->getCustomView());
    }

    public function testVerifyCustomerPhone()
    {
        $this->assertSame($this->request, $this->request->setVerifyCustomerPhone('true'));
        $this->assertSame('true', $this->request->getVerifyCustomerPhone());
    }

    public function testVerifyCustomerEmail()
    {
        $this->assertSame($this->request, $this->request->setVerifyCustomerEmail('true'));
        $this->assertSame('true', $this->request->getVerifyCustomerEmail());
    }

}
