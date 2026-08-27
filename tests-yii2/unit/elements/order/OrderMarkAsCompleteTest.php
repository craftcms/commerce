<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements\order;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use craftcommercetests\fixtures\OrdersFixture;
use UnitTester;

/**
 * OrderMarkAsCompleteTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.2.12
 */
class OrderMarkAsCompleteTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Plugin|null
     */
    protected ?Plugin $pluginInstance = null;

    /**
     * @var array
     */
    private array $_deleteElementIds = [];

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'orders' => [
                'class' => OrdersFixture::class,
            ],
        ];
    }

    /**
     *
     */
    public function testUpdatedProperties(): void
    {
        $order = new Order();
        $email = 'test@newemailaddress.xyz';
        $user = \Craft::$app->getUsers()->ensureUserByEmail($email);
        $order->setCustomer($user);
        /** @var Order $order */
        $completedOrder = $this->tester->grabFixture('orders')->getElement('completed-new');
        $lineItem = $completedOrder->getLineItems()[0];
        $qty = 4;
        $note = 'My note';
        $lineItem = $this->pluginInstance->getLineItems()->create($order, [
                'purchasableId' => $lineItem->purchasableId,
                'qty' => $qty,
                'note' => $note,
            ]);
        $order->setLineItems([$lineItem]);

        self::assertNull($order->dateOrdered);
        self::assertFalse($order->isCompleted);
        self::assertNull($order->orderCompletedEmail);

        self::assertTrue($order->markAsComplete());

        self::assertInstanceOf(\DateTime::class, $order->dateOrdered);
        self::assertTrue($order->isCompleted);
        self::assertEquals($email, $order->orderCompletedEmail);

        $this->_deleteElementIds[] = $order->id;
        $this->_deleteElementIds[] = $user->id;
    }

    /**
     * @return void
     * @throws \Throwable
     * @throws \craft\commerce\errors\CurrencyException
     * @throws \craft\commerce\errors\OrderStatusException
     * @throws \CraftCms\Commerce\Payment\Exceptions\TransactionException
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function testPaidDatesUpdated(): void
    {
        $completedOrder = Order::find()->isCompleted()->isPaid(false)->one();

        $transaction = $this->pluginInstance->getTransactions()->createTransaction($completedOrder, typeOverride: TransactionRecord::TYPE_PURCHASE);
        $transaction->status = TransactionRecord::STATUS_SUCCESS;

        $this->pluginInstance->getTransactions()->saveTransaction($transaction);

        $dateFirstPaid = $transaction->getOrder()->dateFirstPaid;
        $datePaid = $transaction->getOrder()->datePaid;

        self::assertNotNull($dateFirstPaid);
        self::assertNotNull($datePaid);

        // Check date first paid doesn't change if the order is refunded
        $transaction2 = $this->pluginInstance->getTransactions()->createTransaction($completedOrder, parentTransaction: $transaction, typeOverride: TransactionRecord::TYPE_REFUND);
        $transaction2->amount = 10;
        $transaction2->paymentAmount = 10;
        $transaction2->status = TransactionRecord::STATUS_SUCCESS;

        $this->pluginInstance->getTransactions()->saveTransaction($transaction2);

        $refundDateFirstPaid = $transaction2->getOrder()->dateFirstPaid;
        $refundDatePaid = $transaction2->getOrder()->datePaid;

        self::assertEquals($dateFirstPaid->format('Y-m-d H:i:s'), $refundDateFirstPaid->format('Y-m-d H:i:s'));
        self::assertNull($refundDatePaid);
        self::assertNotEquals($datePaid->format('Y-m-d H:i:s'), $refundDatePaid);

        // Check first date paid doesn't change if another payment is made
        $transaction3 = $this->pluginInstance->getTransactions()->createTransaction($completedOrder, typeOverride: TransactionRecord::TYPE_PURCHASE);
        $transaction->paymentAmount = $transaction2->getOrder()->outstandingBalance;
        $transaction->amount = $transaction2->getOrder()->outstandingBalance;
        $transaction3->status = TransactionRecord::STATUS_SUCCESS;

        // Make sure there is a slight delay so the timestamps differ
        sleep(3);

        $this->pluginInstance->getTransactions()->saveTransaction($transaction3);

        $nextDateFirstPaid = $transaction3->getOrder()->dateFirstPaid;
        $nextDatePaid = $transaction3->getOrder()->datePaid;

        self::assertEquals($dateFirstPaid->format('Y-m-d H:i:s'), $nextDateFirstPaid->format('Y-m-d H:i:s'));
        self::assertNotEquals($datePaid->format('Y-m-d H:i:s'), $nextDatePaid->format('Y-m-d H:i:s'));
        self::assertNotNull($nextDatePaid);

        $this->pluginInstance->getTransactions()->deleteTransactionById($transaction->id);
        $this->pluginInstance->getTransactions()->deleteTransactionById($transaction2->id);
        $this->pluginInstance->getTransactions()->deleteTransactionById($transaction3->id);
    }

    #[\Override]
    protected function _before(): void
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();
    }

    #[\Override]
    protected function _after(): void
    {
        parent::_after();

        // Cleanup data.
        foreach ($this->_deleteElementIds as $elementId) {
            \Craft::$app->getElements()->deleteElementById($elementId, null, null, true);
        }
    }
}
