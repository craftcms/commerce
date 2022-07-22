<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use craft\commerce\models\Discount;

/**
 * DiscountTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.0
 */
class DiscountTest extends Unit
{
    /**
     * @dataProvider getPercentDiscountAsPercentDataProvider
     */
    public function testGetPercentDiscountAsPercent($percentDiscount, $expected): void
    {
        $discount = new Discount();
        $discount->percentDiscount = $percentDiscount;

        self::assertSame($expected, $discount->getPercentDiscountAsPercent());
    }

    /**
     * @return array
     */
    public function getPercentDiscountAsPercentDataProvider(): array
    {
        return [
            ['-0.1000', '10%'],
            [0, '0%'],
            [-0.1, '10%'],
            [-0.15, '15%'],
            [-0.105, '10.5%'],
            [-0.10504, '10.504%'],
            ['-0.1050400', '10.504%'],
        ];
    }
}
