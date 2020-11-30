<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Locale;
use UnitTester;

/**
 * LocalesTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.x
 */
class LocalesTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    public function testSetLanguage()
    {
        $order = $this->make(Order::class, ['orderLanguage' => 'en-GB']);

        Locale::switchAppLanguage($order->language);
        self::assertEquals('en-GB', Craft::$app->language);
    }
}
