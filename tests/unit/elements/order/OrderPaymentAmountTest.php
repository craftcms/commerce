<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Craft;
use Codeception\Test\Unit;
use craft\commerce\adjusters\Discount;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\OrderNotice;
use craft\commerce\models\Transaction;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\Plugin;
use UnitTester;

/**
 * OrderTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.3
 */
class OrderPaymentAmountTest extends Unit
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
     * @group PaymentCurrencies
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
        $transaction1->type = TransactionRecord::TYPE_PURCHASE;
        $transaction1->status = TransactionRecord::STATUS_SUCCESS;
        $this->order->setTransactions([$transaction1]);

        self::assertEquals($this->order->getOutstandingBalance(), 8);

        // Add a $2 successful refund transaction to the order
        $transaction2 = new Transaction();
        $transaction2->amount = 2;
        $transaction2->type = TransactionRecord::TYPE_REFUND;
        $transaction2->status = TransactionRecord::STATUS_SUCCESS;
        $this->order->setTransactions([$transaction1, $transaction2]);

        // Paid $12 and refunded $2, order price was $20, but outstanding amount now $10
        self::assertEquals($this->order->getOutstandingBalance(), 10);

        // Payment amount is still 10
        self::assertEquals($this->order->getPaymentAmount(), 10);

        // Setting a payment amount in excess of the outstanding balance is ignored and just set to the outstanding balance
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
