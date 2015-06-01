<?php

namespace Omnipay\Eway\Message;

use Mockery as m;
use Omnipay\Tests\TestCase;

class AbstractRequestTest extends TestCase
{

    public function setUp()
    {
        $this->request = m::mock('\Omnipay\Eway\Message\AbstractRequest')->makePartial();
        $this->request->initialize();
    }

    public function testApiKey()
    {
        $this->assertSame($this->request, $this->request->setApiKey('API KEY'));
        $this->assertSame('API KEY', $this->request->getApiKey());
    }

    public function testPassword()
    {
        $this->assertSame($this->request, $this->request->setPassword('secret'));
        $this->assertSame('secret', $this->request->getPassword());
    }

    public function testPartnerId()
    {
        $this->assertSame($this->request, $this->request->setPartnerId('1234'));
        $this->assertSame('1234', $this->request->getPartnerId());
    }

    public function testTransactionType()
    {
        $this->assertSame($this->request, $this->request->setTransactionType('Purchase'));
        $this->assertSame('Purchase', $this->request->getTransactionType());
    }

    public function testShippingMethod()
    {
        $this->assertSame($this->request, $this->request->setShippingMethod('NextDay'));
        $this->assertSame('NextDay', $this->request->getShippingMethod());
    }

    public function testInvoiceReference()
    {
        $this->assertSame($this->request, $this->request->setInvoiceReference('INV-123'));
        $this->assertSame('INV-123', $this->request->getInvoiceReference());
    }

    public function testGetItemData()
    {
        $this->request->setItems(array(
            array('name' => 'Floppy Disk', 'description' => 'MS-DOS', 'quantity' => 2, 'price' => 10),
            array('name' => 'CD-ROM', 'description' => 'Windows 95', 'quantity' => 1, 'price' => 40),
        ));

        $data = $this->request->getItemData();
        $this->assertSame('Floppy Disk', $data[0]['SKU']);
        $this->assertSame('MS-DOS', $data[0]['Description']);
        $this->assertSame('2', $data[0]['Quantity']);
        $this->assertSame('1000', $data[0]['UnitCost']);

        $this->assertSame('CD-ROM', $data[1]['SKU']);
        $this->assertSame('Windows 95', $data[1]['Description']);
        $this->assertSame('1', $data[1]['Quantity']);
        $this->assertSame('4000', $data[1]['UnitCost']);
    }
}
