<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\elements;

use Codeception\Test\Unit;
use craft\commerce\adjusters\Discount;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\models\OrderNotice;
use craft\commerce\Plugin;
use UnitTester;

/**
 * OrderTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class OrderNoticesTest extends Unit
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
     * @group OrderNotices
     */
    public function testOrderNotices()
    {
        $firstNotice = new OrderNotice();
        $firstNotice->type = 'priceChange';
        $firstNotice->attribute = 'lineItems';
        $firstNotice->message = 'The Price of the product changed.';
        $this->order->addNotice($firstNotice);

        $notices = $this->order->getNotices();
        $firstNotice = $this->order->getFirstNotice();
        self::assertEquals($firstNotice->type, $firstNotice->type);
        self::assertEquals($firstNotice->attribute, $firstNotice->attribute);
        self::assertEquals($firstNotice->message, $firstNotice->message);
        self::assertCount(1, $notices);

        $secondNotice = new OrderNotice();
        $secondNotice->type = 'lineItemRemoved';
        $secondNotice->attribute = 'lineItems';
        $secondNotice->message = 'The x Product is no longer available and has been removed.';
        $this->order->addNotice($secondNotice);

        self::assertCount(1, $notices);
        self::assertCount(2, $this->order->getNotices());

        $this->order->addNotices([$firstNotice, $secondNotice]);
        self::assertCount(4, $this->order->getNotices());
    }

    /**
     * @group OrderNotices
     */
    public function testClearOrderNotices()
    {
        $firstNotice = new OrderNotice();
        $firstNotice->type = 'priceChange';
        $firstNotice->attribute = 'lineItems';
        $firstNotice->message = 'The Price of the product changed.';

        $secondNotice = new OrderNotice();
        $secondNotice->type = 'lineItemRemoved';
        $secondNotice->attribute = 'lineItems';
        $secondNotice->message = 'The x Product is no longer available and has been removed.';

        $this->order->addNotices([$firstNotice, $secondNotice, $firstNotice, $secondNotice]);
        self::assertCount(4, $this->order->getNotices());

        // Test clearing by type
        $this->order->clearNotices('lineItemRemoved');
        self::assertCount(2, $this->order->getNotices());
        $this->order->clearNotices('priceChange');
        self::assertCount(0, $this->order->getNotices());

        // use a third notice
        $thirdNotice = new OrderNotice();
        $thirdNotice->type = 'couponNotValid';
        $thirdNotice->attribute = 'couponCode';
        $thirdNotice->message = 'The x Product is no longer available and has been removed.';

        // Test clearing by attribute
        $this->order->addNotices([$firstNotice, $secondNotice, $firstNotice, $secondNotice, $thirdNotice]);
        self::assertCount(5, $this->order->getNotices());
        $this->order->clearNotices(null, 'lineItems');
        self::assertCount(1, $this->order->getNotices()); // only $thirdNotice should remain

        // test clearing all
        $this->order->addNotices([$firstNotice, $secondNotice, $firstNotice, $secondNotice, $thirdNotice]);
        $this->order->clearNotices();
        self::assertCount(0, $this->order->getNotices()); // only $thirdNotice should remain

        // test clearing using both type and attribute
        $this->order->addNotices([$firstNotice, $secondNotice, $firstNotice, $secondNotice, $thirdNotice]);
        $this->order->clearNotices('lineItemRemoved', 'lineItems');
        self::assertCount(3, $this->order->getNotices()); // only $thirdNotice and

        self::assertTrue($this->order->hasNotices());
        self::assertTrue($this->order->hasNotices('couponNotValid'));
        self::assertCount(1, $this->order->getNotices('couponNotValid'));
        self::assertTrue($this->order->hasNotices(null, 'lineItems'));
        self::assertCount(2, $this->order->getNotices(null, 'lineItems'));
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
