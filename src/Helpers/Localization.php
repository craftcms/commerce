<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Translation\Locale;

abstract class Localization
{
    public static function normalizePercentage(mixed $number): ?float
    {
        if ($number === null) {
            return 0.0;
        }

        if (!is_string($number)) {
            return (float)$number;
        }

        $pct = I18N::getFormattingLocale()->getNumberSymbol(Locale::SYMBOL_PERCENT);
        $number = trim($number, "$pct \t\n\r\0\x0B");

        if ($number === '') {
            return 0.0;
        }

        return (float)I18N::normalizeNumber($number) / 100;
    }
}
