<?php

namespace craftcommerce\tests\unit;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use UnitTester;

class OrderTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var Order
     */
    protected $order;

    public function _before()
    {
        $this->order = new Order();
    }

    public function testOrderSumming()
    {

        $lineItem = new LineItem();
        $lineItem->qty = 2;
        $lineItem->salePrice = 10;
        $this->assertEquals($lineItem->getSubtotal(),  20);



        $this->order->setLineItems([$lineItem]);
        $this->assertEquals($this->order->totalPrice,  20);

    }
}