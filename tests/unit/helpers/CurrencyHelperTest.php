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

    /**
     * @param string $currency
     * @param string $language
     * @param string $expected
     * @return void
     * @dataProvider formatAsCurrencyStripZerosDataProvider
     * @since 5.1.4
     */
    public function testFormatAsCurrencyStripZeros(string $currency, string $language, float $amount, bool $zeros, string $expected): void
    {
        $originalLocale = \Craft::$app->getLocale();
        Locale::switchAppLanguage($language);
        $formattedValue = Currency::formatAsCurrency($amount, $currency, stripZeros: $zeros);

        self::assertEquals($expected, $formattedValue);
        Locale::switchAppLanguage($originalLocale->getLanguageID());
    }

    /**
     * @return array[]
     */
    public function formatAsCurrencyStripZerosDataProvider(): array
    {
        return [
            'USD-US' => [
                'USD',
                'en-US',
                1234.56,
                true,
                '$1,234.56',
            ],
            'USD-US-strip' => [
                'USD',
                'en-US',
                1234.00,
                true,
                '$1,234',
            ],
            'USD-US-no-strip' => [
                'USD',
                'en-US',
                1234.00,
                false,
                '$1,234.00',
            ],
            'USD-GB' => [
                'USD',
                'en-GB',
                1234.56,
                true,
                'US$1,234.56',
            ],
            'USD-GB-strip' => [
                'USD',
                'en-GB',
                1234.0,
                true,
                'US$1,234',
            ],
            'USD-GB-no-strip' => [
                'USD',
                'en-GB',
                1234.0,
                false,
                'US$1,234.00',
            ],
            'USD-FR' => [
                'USD',
                'fr-FR',
                1234.56,
                true,
                '1 234,56 $US',
            ],
            'USD-FR-strip' => [
                'USD',
                'fr-FR',
                1234.00,
                true,
                '1 234 $US',
            ],
            'USD-FR-no-strip' => [
                'USD',
                'fr-FR',
                1234.00,
                false,
                '1 234,00 $US',
            ],
            'EUR-US' => [
                'EUR',
                'en-US',
                1234.56,
                true,
                '€1,234.56',
            ],
            'EUR-US-strip' => [
                'EUR',
                'en-US',
                1234.00,
                true,
                '€1,234',
            ],
            'EUR-US-no-strip' => [
                'EUR',
                'en-US',
                1234.00,
                false,
                '€1,234.00',
            ],
            'EUR-FR' => [
                'EUR',
                'fr-FR',
                1234.56,
                true,
                '1 234,56 €',
            ],
            'EUR-FR-strip' => [
                'EUR',
                'fr-FR',
                1234.00,
                true,
                '1 234 €',
            ],
            'EUR-FR-no-strip' => [
                'EUR',
                'fr-FR',
                1234.00,
                false,
                '1 234,00 €',
            ],
            'EUR-ARABIC' => [
                'EUR',
                'ar',
                1234.56,
                true,
                '١٬٢٣٤٫٥٦ €',
            ],
            'EUR-ARABIC-strip' => [
                'EUR',
                'ar',
                1234.00,
                true,
                '١٬٢٣٤ €',
            ],
            'EUR-ARABIC-no-strip' => [
                'EUR',
                'ar',
                1234.00,
                false,
                '١٬٢٣٤٫٠٠ €',
            ],
        ];
    }
}
