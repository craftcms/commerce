<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\adjusters;

use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use Craft;
use craft\base\Event;
use craft\commerce\adjusters\Shipping;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\events\RegisterAvailableShippingMethodsEvent;
use craft\commerce\models\LineItem;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\services\Discounts;
use craft\commerce\services\ShippingMethods;
use craftcommercetests\fixtures\ProductFixture;
use ReflectionMethod;
use Throwable;
use UnitTester;

/**
 * ShippingTest
 *
 * Covers a real-world bug: a third-party shipping method plugin registers
 * its methods dynamically via
 * {@see ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS} on every
 * call, rather than a static, persisted method. A method later failing to
 * match is normal cart behaviour and not the bug on its own.
 *
 * The real bug: this could happen to an order that had *already completed
 * and been paid for*. `Order::updateOrderPaidInformation()` locks
 * `recalculationMode` to `NONE` while completing an order, but then
 * unconditionally restored whatever mode it had before - `ALL`, since it was
 * a cart a moment ago. Any later save/recalculate (a queue job, webhook, or
 * fulfillment plugin) would then rebuild every adjustment on an already-paid
 * order, and if the third-party shipping method no longer matched, its cost
 * would silently vanish, leaving the order looking overpaid.
 *
 * Fixed by only restoring the original recalculation mode when the call
 * didn't complete the order - see
 * {@see testCompletedAndPaidOrderStaysLockedAgainstRecalculation()} for the
 * fix, and {@see testUpdatingPaidInformationWithoutCompletingStaysRecalculable()}
 * confirming a cart that takes a payment without completing (e.g. a partial
 * payment) stays editable/recalculable as before.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ShippingTest extends Unit
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
     * Toggled mid-test to simulate the registration handler matching the
     * order, then failing to on a later call.
     *
     * @var bool
     */
    private bool $_thirdPartyMethodMatches = true;

    /**
     * @var int[] Element IDs created directly by test methods (not fixtures), for cleanup.
     */
    private array $_deleteElementIds = [];

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'products' => [
                'class' => ProductFixture::class,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->pluginInstance = Plugin::getInstance();
        $this->_thirdPartyMethodMatches = true;

        // No discounts in play; keeps the test isolated from fixture state.
        $this->pluginInstance->set('discounts', $this->make(Discounts::class, [
            'getAllActiveDiscounts' => fn() => [],
        ]));

        // Simulate a third-party plugin registering a shipping method by
        // re-evaluating live availability on every call, instead of
        // returning a static, persisted method.
        Event::on(
            ShippingMethods::class,
            ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS,
            function(RegisterAvailableShippingMethodsEvent $event) {
                $shippingMethods = $event->getShippingMethods();
                $shippingMethods->push($this->make(ShippingMethod::class, [
                    // Plugins must set this themselves on methods they register -
                    // `Order::getAvailableShippingMethodOptions()` silently drops any
                    // `ShippingMethod` whose `storeId` doesn't match the order's.
                    'storeId' => $event->order->storeId,
                    'handle' => 'thirdPartyFlatRate',
                    'name' => 'Third Party Flat Rate',
                    'getIsEnabled' => true,
                    'getMatchingShippingRule' => fn() => null,
                    'getPriceForOrder' => fn() => 8.99,
                    'matchOrder' => fn() => $this->_thirdPartyMethodMatches,
                ]));
            }
        );
    }

    /**
     * @inheritdoc
     */
    protected function _after(): void
    {
        Event::off(ShippingMethods::class, ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS);

        foreach ($this->_deleteElementIds as $elementId) {
            // Pass the element type explicitly: one test saves an anonymous
            // subclass of Order as a spy, whose class name in the `elements`
            // table's `type` column can't be resolved back via `class_exists()`.
            Craft::$app->getElements()->deleteElementById($elementId, Order::class, null, true);
        }
        $this->_deleteElementIds = [];

        parent::_after();
    }

    /**
     * Confirms `Shipping::adjust()` correctly drops the adjustment (no error)
     * when a registered method stops matching. Correct cart behaviour, and
     * not, by itself, the bug.
     */
    public function testAdjusterDropsAdjustmentWhenMethodDoesNotMatch(): void
    {
        $lineItem = $this->make(LineItem::class, [
            'id' => 1,
            'qty' => 1,
            'price' => 50,
            'getIsShippable' => true,
        ]);

        $order = new Order();
        $order->shippingMethodHandle = 'thirdPartyFlatRate';
        $order->setLineItems([$lineItem]);

        $adjuster = new Shipping();

        $firstPass = $adjuster->adjust($order);
        self::assertCount(1, $firstPass, 'Shipping adjustment should be present while the method matches.');
        self::assertEquals(8.99, $firstPass[0]->amount);

        $this->_thirdPartyMethodMatches = false;

        $secondPass = $adjuster->adjust($order);
        self::assertSame([], $secondPass, 'No adjustment, no exception - this part is expected.');
    }

    /**
     * Confirms the fix: an order already completed and paid in full -
     * including the shipping cost - stays locked against recalculation,
     * even after the registration handler stops matching. Before the fix,
     * `recalculate()` would still run in `RECALCULATION_MODE_ALL` here and
     * silently drop the shipping cost from a paid order.
     *
     * @throws Throwable
     */
    public function testCompletedAndPaidOrderStaysLockedAgainstRecalculation(): void
    {
        // A real, saved cart order - `recalculate()` requires one, and only a
        // real element exercises `afterSave()`/`markAsComplete()`/
        // `updateOrderPaidInformation()` the way production code does.
        $order = new Order();
        Craft::$app->getElements()->saveElement($order, false);
        $this->_deleteElementIds[] = $order->id;

        $variant = Variant::find()->indexBy('sku')->all()['hct-white'];
        $lineItem = $this->pluginInstance->getLineItems()->create($order, [
            'purchasableId' => $variant->id,
            'qty' => 1,
            'note' => '',
        ]);
        $order->setLineItems([$lineItem]);
        $order->shippingMethodHandle = 'thirdPartyFlatRate';

        $gateway = $this->pluginInstance->getGateways()->getGatewayByHandle('dummy');
        $order->gatewayId = $gateway->id;

        // Cart is untouched, so recalculation mode defaults to `ALL`, as
        // throughout a normal checkout.
        self::assertEquals(Order::RECALCULATION_MODE_ALL, $order->getRecalculationMode());

        // Checkout: the method matches, its cost gets applied and persisted.
        $order->recalculate();
        Craft::$app->getElements()->saveElement($order, false);

        $totalCollected = $order->getTotalPrice();
        self::assertGreaterThan(0, $order->getTotalShippingCost(), 'Sanity check: shipping cost was applied before payment.');

        // Customer pays the full amount shown at checkout, including shipping.
        $transaction = $this->pluginInstance->getTransactions()->createTransaction($order, typeOverride: TransactionRecord::TYPE_PURCHASE);
        $transaction->status = TransactionRecord::STATUS_SUCCESS;
        $this->pluginInstance->getTransactions()->saveTransaction($transaction);

        // The order is now completed and paid in full...
        self::assertTrue($order->isCompleted);
        self::assertFalse($order->hasOutstandingBalance());
        self::assertEquals($totalCollected, $order->getTotalPaid());

        // ...and, with the fix, stays locked at `NONE` rather than being
        // restored to the `ALL` mode it had as a cart.
        self::assertEquals(
            Order::RECALCULATION_MODE_NONE,
            $order->getRecalculationMode(),
            'A completed order must stay locked against recalculation.'
        );

        // Some time later - a queue job, webhook, or fulfillment plugin -
        // something recalculates this completed order again, and this time
        // the registration handler fails to match. Doesn't matter now,
        // since recalculation is locked out.
        $this->_thirdPartyMethodMatches = false;

        $order->recalculate();

        // Nothing changed: still completed, still paid, shipping cost and
        // handle untouched, no "shippingMethodChanged" notice.
        self::assertTrue($order->isCompleted);
        self::assertEquals($totalCollected, $order->getTotalPrice());
        self::assertEquals($totalCollected, $order->getTotalPaid());
        self::assertEquals('thirdPartyFlatRate', $order->shippingMethodHandle);
        self::assertFalse($order->hasNotices('shippingMethodChanged'));
    }

    /**
     * Control test: proves the bug was real by simulating the old,
     * unconditional restore that `updateOrderPaidInformation()` used to do -
     * manually unlocking a completed, paid order back to
     * `RECALCULATION_MODE_ALL` and saving it. This isn't something the fixed
     * code does; it's here to show the failure mode described in the class
     * docblock actually happens, and that the other tests in this file would
     * catch a regression back to it.
     *
     * @throws Throwable
     */
    public function testManuallyUnlockingRecalculationModeOnCompletedOrderDropsShippingCost(): void
    {
        $order = new Order();
        Craft::$app->getElements()->saveElement($order, false);
        $this->_deleteElementIds[] = $order->id;

        $variant = Variant::find()->indexBy('sku')->all()['hct-white'];
        $lineItem = $this->pluginInstance->getLineItems()->create($order, [
            'purchasableId' => $variant->id,
            'qty' => 1,
            'note' => '',
        ]);
        $order->setLineItems([$lineItem]);
        $order->shippingMethodHandle = 'thirdPartyFlatRate';

        $gateway = $this->pluginInstance->getGateways()->getGatewayByHandle('dummy');
        $order->gatewayId = $gateway->id;

        $order->recalculate();
        Craft::$app->getElements()->saveElement($order, false);

        $totalCollected = $order->getTotalPrice();
        self::assertGreaterThan(0, $order->getTotalShippingCost(), 'Sanity check: shipping cost was applied before payment.');

        $transaction = $this->pluginInstance->getTransactions()->createTransaction($order, typeOverride: TransactionRecord::TYPE_PURCHASE);
        $transaction->status = TransactionRecord::STATUS_SUCCESS;
        $this->pluginInstance->getTransactions()->saveTransaction($transaction);

        self::assertTrue($order->isCompleted);
        self::assertEquals(Order::RECALCULATION_MODE_NONE, $order->getRecalculationMode());

        // Simulate the pre-fix bug: restore the cart's original mode after
        // completion instead of staying locked at `NONE`.
        $order->setRecalculationMode(Order::RECALCULATION_MODE_ALL);

        // The registration handler stops matching, then something saves the
        // order - `afterSave()` unconditionally calls `recalculate()`, which
        // now actually runs, since mode is `ALL` again.
        $this->_thirdPartyMethodMatches = false;
        Craft::$app->getElements()->saveElement($order, false);

        // The shipping cost silently disappeared, even though the order is
        // still marked completed and paid - this is the bug.
        self::assertTrue($order->isCompleted);
        self::assertEquals(0.0, $order->getTotalShippingCost());
        self::assertLessThan($totalCollected, $order->getTotalPrice());
        self::assertGreaterThan($order->getTotalPrice(), $order->getTotalPaid(), 'Order now looks overpaid relative to its (wrongly recalculated) total.');
    }

    /**
     * Confirms the fix doesn't regress the case it must leave alone: a cart
     * that receives a payment/authorization update without completing (e.g.
     * a partial payment) stays fully editable and recalculable, as before.
     *
     * @throws Throwable
     */
    public function testUpdatingPaidInformationWithoutCompletingStaysRecalculable(): void
    {
        $order = new Order();
        Craft::$app->getElements()->saveElement($order, false);
        $this->_deleteElementIds[] = $order->id;

        // A real, priced line item - an empty cart's $0 total is trivially
        // "paid in full", but this needs a genuine amount still owing.
        $variant = Variant::find()->indexBy('sku')->all()['hct-white'];
        $lineItem = $this->pluginInstance->getLineItems()->create($order, [
            'purchasableId' => $variant->id,
            'qty' => 1,
            'note' => '',
        ]);
        $order->setLineItems([$lineItem]);
        $order->recalculate();
        Craft::$app->getElements()->saveElement($order, false);

        self::assertTrue($order->hasOutstandingBalance(), 'Sanity check: the order has an amount still owing.');
        self::assertEquals(Order::RECALCULATION_MODE_ALL, $order->getRecalculationMode());

        // Nothing paid or authorized, so this can't complete the order, but
        // it still exercises the same lock/restore logic that
        // `updateOrderPaidInformation()` runs on every payment update.
        $order->updateOrderPaidInformation();

        self::assertFalse($order->isCompleted, 'Sanity check: nothing was paid, so the order has not completed.');
        self::assertEquals(
            Order::RECALCULATION_MODE_ALL,
            $order->getRecalculationMode(),
            'A cart that receives a payment update without completing must remain fully recalculable.'
        );
    }

    /**
     * Confirms that saving an already-completed, already-paid order again
     * afterwards - as custom code might do, e.g. a controller action or
     * queue job unrelated to shipping - has no adverse effect.
     * `updateOrderPaidInformation()` already saves the order itself; this
     * covers an *extra* save on top of that. Recalculation stays locked at
     * `NONE`, so the extra save is a no-op as far as adjustments go.
     *
     * Also spies on `updateOrderPaidInformation()` itself, to confirm it's
     * actually the successful transaction save that triggers it, rather than
     * this test only happening to reproduce the same end state some other way.
     *
     * @throws Throwable
     */
    public function testSavingCompletedOrderAgainAfterPaymentHasNoAdverseEffect(): void
    {
        // A spy on `updateOrderPaidInformation()`: still runs the real method
        // via reflection (invoking the original, bypassing this override),
        // but additionally expects to be called exactly once. `Expected::once()`
        // is verified automatically when the test finishes. Uses `construct()`
        // rather than `make()` so Order's real constructor/`init()` still runs
        // (e.g. defaulting `siteId`), instead of leaving the order half-built.
        $order = $this->construct(Order::class, [], [
            'updateOrderPaidInformation' => Expected::once(function() use (&$order) {
                (new ReflectionMethod(Order::class, 'updateOrderPaidInformation'))->invoke($order);
            }),
        ]);
        Craft::$app->getElements()->saveElement($order, false);
        $this->_deleteElementIds[] = $order->id;

        $variant = Variant::find()->indexBy('sku')->all()['hct-white'];
        $lineItem = $this->pluginInstance->getLineItems()->create($order, [
            'purchasableId' => $variant->id,
            'qty' => 1,
            'note' => '',
        ]);
        $order->setLineItems([$lineItem]);
        $order->shippingMethodHandle = 'thirdPartyFlatRate';

        $gateway = $this->pluginInstance->getGateways()->getGatewayByHandle('dummy');
        $order->gatewayId = $gateway->id;

        $order->recalculate();
        Craft::$app->getElements()->saveElement($order, false);

        $totalCollected = $order->getTotalPrice();
        $shippingCost = $order->getTotalShippingCost();
        self::assertGreaterThan(0, $shippingCost, 'Sanity check: shipping cost was applied before payment.');

        $transaction = $this->pluginInstance->getTransactions()->createTransaction($order, typeOverride: TransactionRecord::TYPE_PURCHASE);
        $transaction->status = TransactionRecord::STATUS_SUCCESS;
        $this->pluginInstance->getTransactions()->saveTransaction($transaction);

        self::assertTrue($order->isCompleted);

        // The registration handler stops matching some time later - it
        // doesn't matter, because recalculation is locked out.
        $this->_thirdPartyMethodMatches = false;

        // Custom code saves the already-completed, already-paid order again,
        // for reasons unrelated to shipping/adjustments.
        Craft::$app->getElements()->saveElement($order, false);

        self::assertTrue($order->isCompleted);
        self::assertEquals(Order::RECALCULATION_MODE_NONE, $order->getRecalculationMode());
        self::assertEquals($shippingCost, $order->getTotalShippingCost(), 'Shipping cost must survive an unrelated save.');
        self::assertEquals($totalCollected, $order->getTotalPrice());
        self::assertEquals($totalCollected, $order->getTotalPaid());
        self::assertFalse($order->hasOutstandingBalance());
    }
}
