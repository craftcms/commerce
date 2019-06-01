<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommerce\tests\unit;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
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
    // Properties
    // =========================================================================

    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @var Order
     */
    protected $order;

    // Tests
    // =========================================================================

    public function testOrderSumming()
    {
        $lineItem = new LineItem();
        $lineItem->qty = 2;
        $lineItem->salePrice = 10;
        $this->assertEquals($lineItem->getSubtotal(),  20);

        $this->order->setLineItems([$lineItem]);
        $this->assertEquals($this->order->totalPrice,  20);
    }

    // Protected methods
    // =========================================================================

    /**
     *
     */
    protected function _before()
    {
        $this->order = new Order();
    }
}
