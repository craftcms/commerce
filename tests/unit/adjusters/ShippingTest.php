<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\adjusters;

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
use Throwable;
use UnitTester;

/**
 * ShippingTest
 *
 * Covers a real-world bug report: a third-party shipping method plugin
 * registers its shipping methods dynamically via
 * {@see ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS} every
 * time `getMatchingShippingMethods()` is called, rather than storing a
 * static, persisted method. That, by itself, is fine - a plugin not matching
 * an order is expected, ordinary behaviour for a cart.
 *
 * The actual bug was that this could happen to an order that had *already
 * been completed and paid for*. `Order::updateOrderPaidInformation()` locks
 * `recalculationMode` to `NONE` for the duration of marking an order
 * complete, but was then unconditionally restoring whatever mode the order
 * was in *before* it completed - `RECALCULATION_MODE_ALL`, since it was a
 * cart a moment ago. If anything saved/recalculated that same order
 * afterwards (a queue job, a webhook, a fulfillment plugin hooking a
 * payment-complete event), Commerce would wipe and rebuild every adjustment
 * on an order that had already been paid in full - and if the third-party
 * shipping method no longer matched at that point, the shipping cost would
 * silently disappear from a completed order, leaving it looking "overpaid"
 * relative to what was actually collected.
 *
 * Fixed by only restoring the original recalculation mode when the order
 * did *not* complete as a result of the call - see
 * {@see testCompletedAndPaidOrderStaysLockedAgainstRecalculation()} for the
 * fix, and {@see testUpdatingPaidInformationWithoutCompletingStaysRecalculable()}
 * for confirmation that a cart which takes a payment without completing
 * (e.g. a partial payment) remains editable/recalculable as before.
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
     * Toggled mid-test to simulate the third-party plugin's registration
     * handler starting to match the order, then failing to on a later call.
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

        // No discounts in play; keeps the test isolated from DB fixture state.
        $this->pluginInstance->set('discounts', $this->make(Discounts::class, [
            'getAllActiveDiscounts' => fn() => [],
        ]));

        // Simulate a third-party plugin that registers a shipping method by
        // re-evaluating live availability every time it's asked, instead of
        // returning a static, persisted method.
        Event::on(
            ShippingMethods::class,
            ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS,
            function(RegisterAvailableShippingMethodsEvent $event) {
                $shippingMethods = $event->getShippingMethods();
                $shippingMethods->push($this->make(ShippingMethod::class, [
                    // Third-party plugins have to set this themselves on the methods they
                    // register (e.g. Postie's `Service::registerShippingMethods()` does the
                    // same) - `Order::getAvailableShippingMethodOptions()` silently drops any
                    // `ShippingMethod` instance whose `storeId` doesn't match the order's.
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
            Craft::$app->getElements()->deleteElementById($elementId, null, null, true);
        }
        $this->_deleteElementIds = [];

        parent::_after();
    }

    /**
     * Building block: confirms `Shipping::adjust()` in isolation does exactly
     * what it should when a registered method stops matching - drop the
     * adjustment, no error. This is *correct* behaviour for a cart, and is
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
     * Confirms the fix: a real order that has already been completed *and
     * paid in full* - including the third-party shipping cost - stays
     * locked against recalculation, even when the third-party plugin's
     * handler later stops matching. Before the fix, `recalculate()` would
     * still run in `RECALCULATION_MODE_ALL` here and silently drop the
     * shipping cost from a paid order.
     *
     * @throws Throwable
     */
    public function testCompletedAndPaidOrderStaysLockedAgainstRecalculation(): void
    {
        // A real, saved cart order - `recalculate()` requires a saved order,
        // and only a real element exercises `afterSave()`/`markAsComplete()`/
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

        // Cart is untouched, so recalculation mode defaults to `ALL` - this
        // is the same state the order is in throughout a normal checkout.
        self::assertEquals(Order::RECALCULATION_MODE_ALL, $order->getRecalculationMode());

        // Checkout: the third-party method matches, its cost gets applied and persisted.
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

        // Some time later - a queue job, a webhook, a fulfillment plugin
        // reacting to payment completion - something recalculates this same
        // completed order again. This time the third-party plugin's
        // registration handler fails to match (its own route/context check
        // fails outside the checkout flow, or its live rate lookup errors).
        // None of that matters now, because recalculation is locked out.
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
     * Confirms the fix doesn't regress the case it needs to leave alone: a
     * cart that receives a payment/authorization update but does *not*
     * complete as a result (e.g. a partial payment) must remain fully
     * editable and recalculable, exactly as before the fix.
     *
     * @throws Throwable
     */
    public function testUpdatingPaidInformationWithoutCompletingStaysRecalculable(): void
    {
        $order = new Order();
        Craft::$app->getElements()->saveElement($order, false);
        $this->_deleteElementIds[] = $order->id;

        // A real, priced line item - an empty cart has a $0 total, which is
        // trivially "paid in full" with no outstanding balance. That's not
        // what we're testing here: this needs a genuine amount still owing.
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

        // Nothing paid or authorized, so this cannot complete the order - it
        // still exercises the same lock/restore logic `updateOrderPaidInformation()`
        // runs through on every payment/authorization update, successful or not.
        $order->updateOrderPaidInformation();

        self::assertFalse($order->isCompleted, 'Sanity check: nothing was paid, so the order has not completed.');
        self::assertEquals(
            Order::RECALCULATION_MODE_ALL,
            $order->getRecalculationMode(),
            'A cart that receives a payment update without completing must remain fully recalculable.'
        );
    }
}
