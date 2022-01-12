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

        $pct = Craft::$app->getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $number = trim($number, "$pct \t\n\r\0\x0B");

        if ($number === '') {
            return 0.0;
        }

        return static::normalizeNumber($number) / 100;
    }

    /**
     * Return value as a percentage.
     *
     * Decimal precision is dynamically calculated when `$decimals` is `null`.
     *
     * @param int|float|string $value the value to be formatted. It must be a factor e.g. `0.75` will result in `75%`.
     * @param int|null $decimals the number of digits after the decimal point.
     * @param array $options optional configuration for the number formatter. This parameter will be merged with [[numberFormatterOptions]].
     * @param array $textOptions optional configuration for the number formatter. This parameter will be merged with [[numberFormatterTextOptions]].
     * @return string the formatted result.
     */
    public static function formatAsPercentage($value, ?int $decimals = null, array $options = [], array $textOptions = []): string
    {
        if ($value === null || $value === '') {
            $value = 0;
        }

        if ($decimals === null) {
            $decimals = strpos(strrev($value * 100), '.') ?: 0;
        }

        return Craft::$app->getFormatter()->asPercent($value, $decimals, $options, $textOptions);
    }
}
