<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace unit\services;

use Codeception\Test\Unit;
use craft\base\Event;
use craft\commerce\elements\Order;
use craft\commerce\events\RegisterAvailableShippingMethodsEvent;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin;
use craft\commerce\services\ShippingMethods;
use UnitTester;

/**
 * Class ShippingMethodsTest
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class ShippingMethodsTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var ShippingMethods
     */
    protected $shippingMethods;


    public function _before()
    {
        parent::_before();

        $this->shippingMethods = Plugin::getInstance()->getShippingMethods();
    }

    public function testGetMatchingShippingMethods(): void
    {
        $order = new Order();

        Event::on(ShippingMethods::class, ShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, function(RegisterAvailableShippingMethodsEvent $event) {
            $shippingMethods = $event->getShippingMethods();

            $shippingMethods->push($this->make(ShippingMethod::class, [
                'name' => 'First',
                'handle' => 'first',
                'getPriceForOrder' => 12.34,
                'getIsEnabled' => true,
                'matchOrder' => true,
            ]));
            $shippingMethods->push($this->make(ShippingMethod::class, [
                'name' => 'Second',
                'handle' => 'second',
                'getPriceForOrder' => 12.35,
                'getIsEnabled' => true,
                'matchOrder' => true,
            ]));
            $shippingMethods->push($this->make(ShippingMethod::class, [
                'name' => 'Really First',
                'handle' => 'reallyFirst',
                'getPriceForOrder' => 12.33,
                'getIsEnabled' => true,
                'matchOrder' => true,
            ]));
        });

        $matchingMethods = $this->shippingMethods->getMatchingShippingMethods($order);

        self::assertEquals(['reallyFirst', 'first', 'second'], array_keys($matchingMethods));
    }
}
