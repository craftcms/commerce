<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\Transaction;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craftcommercetests\fixtures\PaymentCurrenciesFixture;
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
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'payment-currencies' => [
                'class' => PaymentCurrenciesFixture::class,
            ],
        ];
    }

    /**
     * @group PaymentCurrencies
     */
    public function testOrderPaymentAmounts(): void
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
     * @dataProvider isPaymentAmountPartialDataProvider
     */
    public function testIsPaymentAmountPartial($lineItems, $paymentAmount, $paymentCurrency, $isPartial)
    {
        foreach ($lineItems as &$item) {
            $item = Craft::createObject(LineItem::class, [
                'config' => ['attributes' => $item],
            ]);
        }
        unset($item);

        $this->order->setLineItems($lineItems);
        $this->order->setPaymentCurrency($paymentCurrency);

        if ($paymentAmount !== null) {
            $this->order->setPaymentAmount($paymentAmount);
        }

        self::assertEquals($isPartial, $this->order->isPaymentAmountPartial());
    }

    /**
     * @return array[]
     */
    public function isPaymentAmountPartialDataProvider()
    {
        $lineItems = [
            'first' => [
                'qty' => 1,
                'salePrice' => 10,
            ],
            'second' => [
                'qty' => 1,
                'salePrice' => 20,
            ],
        ];

        return [
            'partial-payment' => [
                $lineItems,
                10,
                'AUD',
                true,
            ],
            'full-payment-specified' => [
                array_merge($lineItems, ['second' => ['salePrice' => 7.75, 'qty' => 1]]),
                23.08,
                'AUD',
                false,
            ],
            'currency-specified-but-no-amount' => [
                $lineItems,
                null,
                'AUD',
                false,
            ],
        ];
    }

    /**
     *
     */
    protected function _before(): void
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();

        $this->order = new Order();
    }

    /**
     *
     */
    protected function _after(): void
    {
        parent::_after();
    }
}
