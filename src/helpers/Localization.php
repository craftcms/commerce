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
}
