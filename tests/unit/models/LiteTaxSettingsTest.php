<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\models;

use Codeception\Test\Unit;
use craft\commerce\models\LiteTaxSettings;

/**
 * LiteTaxSettingsTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.4.10.1
 */
class LiteTaxSettingsTest extends Unit
{
    /**
     * @dataProvider getTaxRateAsPercentDataProvider
     */
    public function testGetTaxRateAsPercent($taxRate, $expected): void
    {
        $liteTaxSettings = new LiteTaxSettings();
        $liteTaxSettings->taxRate = $taxRate;

        self::assertSame($expected, $liteTaxSettings->getTaxRateAsPercent());
    }

    /**
     * @return array
     */
    public function getTaxRateAsPercentDataProvider(): array
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