<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\adjusters\Discount;
use craft\commerce\adjusters\Tax;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use UnitTester;

/**
 * OrderTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
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

    /**
     * @var string
     */
    protected $originalEdition;

    /**
     *
     */
    protected $pluginInstance;

    /**
     *
     */
    public function testOrderSumTotalPrice()
    {
        $lineItem1 = new LineItem();
        $lineItem1->qty = 2;
        $lineItem1->salePrice = 10;
        $this->assertEquals($lineItem1->getSubtotal(),  20);

        $lineItem2 = new LineItem();
        $lineItem2->qty = 3;
        $lineItem2->salePrice = 20;
        $this->assertEquals($lineItem2->getSubtotal(),  60);

        $this->order->setLineItems([$lineItem1, $lineItem2]);
        $this->assertEquals($this->order->getTotalPrice(),  80);

        $adjustment1 = new OrderAdjustment();
        $adjustment1->amount = -10;
        $adjustment1->type = Discount::ADJUSTMENT_TYPE;
        $adjustment1->setLineItem($lineItem1);
        $adjustment1->name = 'Discount';
        $adjustment1->description = '10 bucks off';
        $adjustment1->setOrder($this->order);
        $this->order->setAdjustments([$adjustment1]);

        $this->assertEquals($this->order->getTotalPrice(),  70);

        $adjustment2 = new OrderAdjustment();
        $adjustment2->amount = -5;
        $adjustment1->type = Discount::ADJUSTMENT_TYPE;
        $adjustment2->setLineItem($lineItem2);
        $adjustment2->name = 'Discount';
        $adjustment2->description = '5 bucks off';
        $adjustment2->setOrder($this->order);

        $this->order->setAdjustments([$adjustment1, $adjustment2]);
        $this->assertEquals($this->order->getTotalPrice(),  65);

        $adjustment3 = new OrderAdjustment();
        $adjustment3->amount = 5;
        $adjustment3->setLineItem($lineItem2);
        $adjustment3->name = 'Tax';
        $adjustment3->description = '5 buck tax';
        $adjustment3->included = true;
        $adjustment3->setOrder($this->order);

        $this->order->setAdjustments([$adjustment1, $adjustment2, $adjustment3]);
        $this->assertEquals($this->order->getTotalPrice(),  65);
    }


    /**
     *
     */
    protected function _before()
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();
        $this->originalEdition = $this->pluginInstance->edition;
        $this->pluginInstance->edition = Plugin::EDITION_PRO;

        $this->order = new Order();
    }

    protected function _after()
    {
        parent::_after();

        $this->pluginInstance->edition = $this->originalEdition;
    }
}
