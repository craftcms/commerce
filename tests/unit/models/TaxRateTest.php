<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use craft\commerce\models\TaxRate;

/**
 * TaxRateTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.4.10.1
 */
class TaxRateTest extends Unit
{
    /**
     * @dataProvider getRateAsPercentDataProvider
     */
    public function testGetRateAsPercent($rate, $expected): void
    {
        $taxRate = new TaxRate();
        $taxRate->rate = $rate;

        self::assertSame($expected, $taxRate->getRateAsPercent());
    }

    /**
     * @return array
     */
    public function getRateAsPercentDataProvider(): array
    {
        return [
            ['0.1000', '10%'],
            [0, '0%'],
            [0.1, '10%'],
            [0.15, '15%'],
            [0.105, '10.5%'],
            [0.10504, '10.504%'],
            ['0.1050400', '10.504%'],
        ];
    }
}
