<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\helpers;

use Codeception\Test\Unit;
use Craft;
use craft\commerce\helpers\Localization;
use UnitTester;

/**
 * LocaleHelperTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2.14
 */
class LocalizationHelperTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @param float $expected
     * @param int|float|string|null $number
     * @dataProvider normalizePercentageDataProvider
     */
    public function testNormalizePercentage(float $expected, $number): void
    {
        self::assertEquals($expected, Localization::normalizePercentage($number));
    }

    /**
     * @return array
     */
    public function normalizePercentageDataProvider(): array
    {
        $pct = Craft::$app->getLocale()->getNumberSymbol(\craft\i18n\Locale::SYMBOL_PERCENT);
        return [
            [0.0, null],
            [0.0, ''],
            [0.0, $pct],
            [0.0, " $pct "],
            [0.0, 0],
            [0.5, 0.5],
            [50.0, 50],
            [1.0, 1],
            [0.0, '0'],
            [0.01, '1'],
            [0.5, '50'],
            [0.0, ' 0.0 '],
            [0.005, " .5 $pct "],
            [0.005, " $pct 0.5 "],
        ];
    }
}
