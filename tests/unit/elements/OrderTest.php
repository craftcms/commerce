<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
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
    public function testOrderSumTotalPriceCorrectly()
    {
        $lineItem1 = new LineItem();
        $lineItem1->qty = 2;
        $lineItem1->salePrice = 10;
        $this->assertEquals($lineItem1->getSubtotal(),  20);

        $lineItem2 = new LineItem();
        $lineItem2->qty = 3;
        $lineItem2->salePrice = 20;
        $this->assertEquals($lineItem2->getSubtotal(),  60);

        $this->order->setLineItems([$lineItem1, $lineItem2]);
        $this->assertEquals($this->order->totalPrice,  80);
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

    protected function _after()
    {
        parent::_after();

        $this->pluginInstance->edition = $this->originalEdition;
    }
}
