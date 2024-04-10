<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\helpers;

use Codeception\Test\Unit;
use craft\commerce\errors\CurrencyException;
use craft\commerce\helpers\Currency;
use craft\commerce\helpers\Locale;
use UnitTester;
use yii\base\InvalidConfigException;

/**
 * CurrencyHelperTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 4.5.4
 */
class CurrencyHelperTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected $tester;

    /**
     * @param string $currency
     * @param string $language
     * @param string $expected
     * @return void
     * @throws InvalidConfigException
     * @throws CurrencyException
     * @dataProvider formatAsCurrencyDataProvider
     */
    public function testFormatAsCurrency(string $currency, string $language, string $expected): void
    {
        $originalLocale = \Craft::$app->getLocale();
        Locale::switchAppLanguage($language);
        $amount = 1234.56;
        $formattedValue = Currency::formatAsCurrency($amount, $currency);

        self::assertEquals($expected, $formattedValue);
        Locale::switchAppLanguage($originalLocale->getLanguageID());
    }

    public function formatAsCurrencyDataProvider(): array
    {
        return [
            'USD-US' => [
                'USD',
                'en-US',
                '$1,234.56',
            ],
            'USD-GB' => [
                'USD',
                'en-GB',
                'US$1,234.56',
            ],
            'USD-FR' => [
                'USD',
                'fr-FR',
                '1 234,56 $US',
            ],
            'EUR-US' => [
                'EUR',
                'en-US',
                '€1,234.56',
            ],
            'EUR-GB' => [
                'EUR',
                'en-GB',
                '€1,234.56',
            ],
            'EUR-FR' => [
                'EUR',
                'fr-FR',
                '1 234,56 €',
            ],
        ];
    }
}
