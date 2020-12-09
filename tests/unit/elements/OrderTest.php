<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements;

use Codeception\Test\Unit;
use craft\commerce\adjusters\Discount;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\Transaction;
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
     *
     */
    public function testOrderPaymentAmounts()
    {
        $this->order = new Order();
        $this->order->id = 1000;

        $lineItem = new LineItem();
        $lineItem->salePrice = 10;
        $lineItem->qty = 2;
        $this->order->setLineItems([$lineItem]);

        // We have an amount to owe on this order
        self::assertTrue($this->order->hasOutstandingBalance());

        // Amount owed is the payment amount
        self::assertEquals($this->order->getPaymentAmount(), $this->order->getOutstandingBalance());

        // Check setter/getter is working
        $amountToPay = 10;
        $this->order->setPaymentAmount($amountToPay);
        self::assertEquals($this->order->getPaymentAmount(), $amountToPay);

        // Add a $12 successful transaction to the order
        $transaction1 = new Transaction();
        $transaction1->amount = 12;
        $transaction1->type = \craft\commerce\records\Transaction::TYPE_PURCHASE;
        $transaction1->status = \craft\commerce\records\Transaction::STATUS_SUCCESS;
        $this->order->setTransactions([$transaction1]);

        self::assertEquals($this->order->getOutstandingBalance(), 8);

        // Add a $2 successful refund transaction to the order
        $transaction2 = new Transaction();
        $transaction2->amount = 2;
        $transaction2->type = \craft\commerce\records\Transaction::TYPE_REFUND;
        $transaction2->status = \craft\commerce\records\Transaction::STATUS_SUCCESS;
        $this->order->setTransactions([$transaction1, $transaction2]);

        // Paid $12 and refunded $2, order price was $20, but outstanding amount now $10
        self::assertEquals($this->order->getOutstandingBalance(), 10);

        // Payment amount is still 10
        self::assertEquals($this->order->getPaymentAmount(), 10);

        // Setting a payment amount in excess of the outstanding balance is ignore and just set to the outstanding balance
        $this->order->setPaymentAmount(1000);
        self::assertEquals($this->order->getPaymentAmount(), $this->order->getOutstandingBalance());
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

    /**
     *
     */
    protected function _after()
    {
        parent::_after();

        $this->pluginInstance->edition = $this->originalEdition;
    }
}
