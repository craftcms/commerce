<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use craft\commerce\adjusters\Discount;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\Plugin;
use UnitTester;

/**
 * OrderTotalsTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 2.1
 */
class OrderTotalsTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Order
     */
    protected Order $order;

    /**
     * @var Plugin|null
     */
    protected ?Plugin $pluginInstance;

    /**
     *
     */
    public function testOrderSumTotalPrice(): void
    {
        $lineItem1 = new LineItem();
        $lineItem1->qty = 2;
        $lineItem1->salePrice = 10;
        self::assertEquals(20, $lineItem1->getSubtotal());

        $lineItem2 = new LineItem();
        $lineItem2->qty = 3;
        $lineItem2->salePrice = 20;
        self::assertEquals(60, $lineItem2->getSubtotal());

        $this->order->setLineItems([$lineItem1, $lineItem2]);
        self::assertEquals(80, $this->order->getTotalPrice());

        $adjustment1 = new OrderAdjustment();
        $adjustment1->amount = -10;
        $adjustment1->type = Discount::ADJUSTMENT_TYPE;
        $adjustment1->setLineItem($lineItem1);
        $adjustment1->name = 'Discount';
        $adjustment1->description = '10 bucks off';
        $adjustment1->setOrder($this->order);
        $this->order->setAdjustments([$adjustment1]);

        self::assertEquals(70, $this->order->getTotalPrice());

        $adjustment2 = new OrderAdjustment();
        $adjustment2->amount = -5;
        $adjustment1->type = Discount::ADJUSTMENT_TYPE;
        $adjustment2->setLineItem($lineItem2);
        $adjustment2->name = 'Discount';
        $adjustment2->description = '5 bucks off';
        $adjustment2->setOrder($this->order);

        $this->order->setAdjustments([$adjustment1, $adjustment2]);
        self::assertEquals(65, $this->order->getTotalPrice());

        $adjustment3 = new OrderAdjustment();
        $adjustment3->amount = 5;
        $adjustment3->setLineItem($lineItem2);
        $adjustment3->name = 'Tax';
        $adjustment3->description = '5 buck tax';
        $adjustment3->included = true;
        $adjustment3->setOrder($this->order);

        $this->order->setAdjustments([$adjustment1, $adjustment2, $adjustment3]);
        self::assertEquals(65, $this->order->getTotalPrice());
    }

    /**
     * @inheritdoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();

        $this->order = new Order();
    }

    /**
     * @inheritdoc
     */
    protected function _after(): void
    {
        parent::_after();
    }
}
