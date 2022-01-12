<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\i18n\Locale;

/**
 * Localization Helper
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.4.10
 */
abstract class Localization extends \craft\helpers\Localization
{
    /**
     * Normalizes a percentage value into a float.
     *
     * @param int|float|string|null $number
     * @return float
     */
    public static function normalizePercentage($number): ?float
    {
        if ($number === null) {
            return 0.0;
        }

        if (!is_string($number)) {
            return (float)$number;
        }

        $number = trim($number);

        // Is there a % symbol?
        $pct = Craft::$app->getLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        if ($trimmedPct = (($trimmedNumber = trim($number, "$pct \t\n\r\0\x0B")) !== $number)) {
            $number = $trimmedNumber;
        }

        if ($number === '') {
            return 0.0;
        }

        $number = (float)static::normalizeNumber($number);

        if ($trimmedPct || $number >= 1) {
            $number /= 100;
        }

        return $number;
    }

    /**
     * Return value as a percentage.
     *
     * Decimal precision is dynamically calculated when `$decimals` is `null`.
     *
     * @param mixed $value the value to be formatted. It must be a factor e.g. `0.75` will result in `75%`.
     * @param int|null $decimals the number of digits after the decimal point.
     * @param array $options optional configuration for the number formatter. This parameter will be merged with [[numberFormatterOptions]].
     * @param array $textOptions optional configuration for the number formatter. This parameter will be merged with [[numberFormatterTextOptions]].
     * @return string the formatted result.
     */
    public static function asPercent($value, $decimals = null, $options = [], $textOptions = []): string
    {
        if ($decimals === null) {
            $fullValue = ($value * 100);
            $decimals = strpos($fullValue, '.') !== false ? strlen($fullValue) - strpos($fullValue, '.') - 1 : 0;
        }

        return Craft::$app->getFormatter()->asPercent($value, $decimals, $options, $textOptions);
    }
}
