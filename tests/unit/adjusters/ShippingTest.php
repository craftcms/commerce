<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\adjusters;

use Codeception\Test\Unit;
use craft\base\Event;
use craft\commerce\adjusters\Shipping;
use craft\commerce\elements\Order;
use craft\commerce\events\RegisterAvailableShippingMethodsEvent;
use craft\commerce\models\LineItem;
use craft\commerce\models\ShippingMethod;
use craft\commerce\services\ShippingMethods;
use UnitTester;

/**
 * ShippingTest
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
     * Toggled mid-test to simulate the registration handler matching the
     * order, then failing to on a later call.
     *
     * @var bool
     */
    private bool $_thirdPartyMethodMatches = true;

    /**
     * @inheritdoc
     */
    protected function _before(): void
    {
        parent::_before();

        $this->_thirdPartyMethodMatches = true;

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
                    'getMatchingShippingRule' => fn(Order $order) => null,
                    'getPriceForOrder' => fn(Order $order) => 8.99,
                    'matchOrder' => fn(Order $order) => $this->_thirdPartyMethodMatches,
            }
        );
    }

    /**
     * @inheritdoc
     */
    protected function _after(): void
    {
        Event::off(ShippingMethods::class, ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS);

        parent::_after();
    }

    /**
     * Confirms `Shipping::adjust()` correctly drops the adjustment (no error)
     * when a registered method stops matching. Correct cart behaviour, and
     * not, by itself, a bug - see
     * {@see \craftcommercetests\unit\elements\order\OrderRecalculationTest}
     * for the recalculation-lock bug this scenario was originally written to
     * demonstrate.
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
}
