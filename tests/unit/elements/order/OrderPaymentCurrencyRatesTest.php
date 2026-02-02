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
use craft\commerce\Plugin;
use craftcommercetests\fixtures\OrdersFixture;
use craftcommercetests\fixtures\PaymentCurrenciesFixture;
use UnitTester;

/**
 * OrderPaymentCurrencyRatesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 5.6
 */
class OrderPaymentCurrencyRatesTest extends Unit
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
            'payment-currencies' => [
                'class' => PaymentCurrenciesFixture::class,
            ],
        ];
    }

    /**
     * Test that payment currency rates are captured when an order is marked as complete.
     *
     * @group PaymentCurrencyRates
     */
    public function testPaymentCurrencyRatesCapturedOnCompletion(): void
    {
        $order = new Order();
        $email = 'test-rates@example.com';
        $user = Craft::$app->getUsers()->ensureUserByEmail($email);
        $order->setCustomer($user);

        /** @var Order $completedOrder */
        $completedOrder = $this->tester->grabFixture('orders')->getElement('completed-new');
        $lineItem = $completedOrder->getLineItems()[0];
        $lineItem = $this->pluginInstance->getLineItems()->create($order, [
            'purchasableId' => $lineItem->purchasableId,
            'qty' => 1,
            'note' => '',
        ]);
        $order->setLineItems([$lineItem]);

        // Before completion, rates should be null
        self::assertNull($order->paymentCurrencyRates);

        // Mark order as complete
        self::assertTrue($order->markAsComplete());

        // After completion, rates should be captured
        self::assertNotNull($order->paymentCurrencyRates);
        self::assertIsArray($order->paymentCurrencyRates);

        // Should have EUR and AUD from fixtures
        self::assertArrayHasKey('EUR', $order->paymentCurrencyRates);
        self::assertArrayHasKey('AUD', $order->paymentCurrencyRates);

        // Verify the rates match the fixture data
        self::assertEquals(0.5, $order->paymentCurrencyRates['EUR']);
        self::assertEquals(1.3, $order->paymentCurrencyRates['AUD']);

        $this->_deleteElementIds[] = $order->id;
        $this->_deleteElementIds[] = $user->id;
    }

    /**
     * Test the setPaymentCurrencyRates setter with different input types.
     *
     * @dataProvider setPaymentCurrencyRatesDataProvider
     * @group PaymentCurrencyRates
     */
    public function testSetPaymentCurrencyRates(mixed $input, ?array $expected): void
    {
        $order = new Order();
        $order->setPaymentCurrencyRates($input);

        self::assertEquals($expected, $order->paymentCurrencyRates);
    }

    /**
     * @return array
     */
    public function setPaymentCurrencyRatesDataProvider(): array
    {
        return [
            'null-input' => [
                null,
                null,
            ],
            'array-input' => [
                ['EUR' => 0.85, 'GBP' => 0.73],
                ['EUR' => 0.85, 'GBP' => 0.73],
            ],
            'json-string-input' => [
                '{"EUR":0.85,"GBP":0.73}',
                ['EUR' => 0.85, 'GBP' => 0.73],
            ],
            'empty-array' => [
                [],
                [],
            ],
            'empty-json' => [
                '{}',
                [],
            ],
        ];
    }

    /**
     * Test that getPaymentAmount uses snapshotted rates when store setting is enabled.
     *
     * @group PaymentCurrencyRates
     */
    public function testGetPaymentAmountUsesSnapshotRateWhenEnabled(): void
    {
        $order = new Order();
        $order->id = 9999;
        $order->isCompleted = true;
        $order->currency = 'USD';

        // Set up a line item with a known price
        $lineItem = new LineItem();
        $lineItem->price = 100;
        $lineItem->qty = 1;
        $order->setLineItems([$lineItem]);

        // Set snapshotted rate for EUR (different from fixture rate of 0.5)
        $snapshotRate = 0.8;
        $order->setPaymentCurrencyRates(['EUR' => $snapshotRate, 'AUD' => 1.5]);

        // Set payment currency to EUR
        $order->setPaymentCurrency('EUR');

        // Get the store and enable the snapshot setting
        $store = $order->getStore();
        $store->setUsesSnapshotPaymentCurrencyRate(true);

        // The payment amount should use the snapshotted rate
        // Outstanding balance is 100 USD, snapshotted EUR rate is 0.8
        // So payment amount should be 100 * 0.8 = 80 EUR
        $paymentAmount = $order->getPaymentAmount();

        self::assertEquals(80, $paymentAmount);
    }

    /**
     * Test that getPaymentAmount uses current rates when store setting is disabled.
     *
     * @group PaymentCurrencyRates
     */
    public function testGetPaymentAmountUsesCurrentRateWhenDisabled(): void
    {
        $order = new Order();
        $order->id = 9998;
        $order->isCompleted = true;
        $order->currency = 'USD';

        // Set up a line item with a known price
        $lineItem = new LineItem();
        $lineItem->price = 100;
        $lineItem->qty = 1;
        $order->setLineItems([$lineItem]);

        // Set snapshotted rate for EUR (different from fixture rate of 0.5)
        $snapshotRate = 0.8;
        $order->setPaymentCurrencyRates(['EUR' => $snapshotRate]);

        // Set payment currency to EUR
        $order->setPaymentCurrency('EUR');

        // Get the store and disable the snapshot setting
        $store = $order->getStore();
        $store->setUsesSnapshotPaymentCurrencyRate(false);

        // The payment amount should use the current rate from fixtures (0.5)
        // Outstanding balance is 100 USD, current EUR rate is 0.5
        // So payment amount should be 100 * 0.5 = 50 EUR
        $paymentAmount = $order->getPaymentAmount();

        self::assertEquals(50, $paymentAmount);
    }

    /**
     * Test that incomplete orders don't use snapshotted rates even if setting is enabled.
     *
     * @group PaymentCurrencyRates
     */
    public function testIncompleteOrderDoesNotUseSnapshotRate(): void
    {
        $order = new Order();
        $order->id = 9997;
        $order->isCompleted = false; // Order not completed
        $order->currency = 'USD';

        // Set up a line item with a known price
        $lineItem = new LineItem();
        $lineItem->price = 100;
        $lineItem->qty = 1;
        $order->setLineItems([$lineItem]);

        // Set snapshotted rate (shouldn't be used)
        $order->setPaymentCurrencyRates(['EUR' => 0.8]);

        // Set payment currency to EUR
        $order->setPaymentCurrency('EUR');

        // Get the store and enable the snapshot setting
        $store = $order->getStore();
        $store->setUsesSnapshotPaymentCurrencyRate(true);

        // The payment amount should use the current rate because order is not completed
        // Current EUR rate from fixtures is 0.5
        $paymentAmount = $order->getPaymentAmount();

        self::assertEquals(50, $paymentAmount);
    }

    /**
     * Test that orders without snapshotted rates fall back to current rates.
     *
     * @group PaymentCurrencyRates
     */
    public function testOrderWithoutSnapshotFallsBackToCurrentRate(): void
    {
        $order = new Order();
        $order->id = 9996;
        $order->isCompleted = true;
        $order->currency = 'USD';

        // Set up a line item with a known price
        $lineItem = new LineItem();
        $lineItem->price = 100;
        $lineItem->qty = 1;
        $order->setLineItems([$lineItem]);

        // Don't set snapshotted rates (simulating old order before feature)
        $order->paymentCurrencyRates = null;

        // Set payment currency to EUR
        $order->setPaymentCurrency('EUR');

        // Get the store and enable the snapshot setting
        $store = $order->getStore();
        $store->setUsesSnapshotPaymentCurrencyRate(true);

        // The payment amount should use the current rate because no snapshot exists
        $paymentAmount = $order->getPaymentAmount();

        self::assertEquals(50, $paymentAmount);
    }

    /**
     * Test Store model getter/setter for usesSnapshotPaymentCurrencyRate.
     *
     * @dataProvider storeUsesSnapshotPaymentCurrencyRateDataProvider
     * @group PaymentCurrencyRates
     */
    public function testStoreUsesSnapshotPaymentCurrencyRateSetting(bool|string $input, bool $expectedParsed, bool|string $expectedRaw): void
    {
        $store = $this->pluginInstance->getStores()->getPrimaryStore();
        $store->setUsesSnapshotPaymentCurrencyRate($input);

        // Test parsed value (default behavior)
        self::assertEquals($expectedParsed, $store->getUsesSnapshotPaymentCurrencyRate());

        // Test raw value (unparsed)
        self::assertEquals($expectedRaw, $store->getUsesSnapshotPaymentCurrencyRate(false));
    }

    /**
     * @return array
     */
    public function storeUsesSnapshotPaymentCurrencyRateDataProvider(): array
    {
        return [
            'true-boolean' => [true, true, true],
            'false-boolean' => [false, false, false],
            'true-string' => ['true', true, 'true'],
            'false-string' => ['false', false, 'false'],
            '1-string' => ['1', true, '1'],
            '0-string' => ['0', false, '0'],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();
    }

    /**
     * @inheritdoc
     */
    protected function _after(): void
    {
        parent::_after();

        // Cleanup data.
        foreach ($this->_deleteElementIds as $elementId) {
            Craft::$app->getElements()->deleteElementById($elementId, null, null, true);
        }
    }
}
