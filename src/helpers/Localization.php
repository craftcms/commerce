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
}
