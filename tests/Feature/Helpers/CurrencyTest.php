<?php

declare(strict_types=1);

use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Helpers\Locale;

afterEach(function () {
    Locale::switchAppLanguage('en-US');
});

test('formatAsCurrency', function (string $currency, string $language, string $expected) {
    Locale::switchAppLanguage($language);

    expect(Currency::formatAsCurrency(1234.56, $currency))->toBe($expected);
})->with([
    'USD-US' => ['USD', 'en-US', '$1,234.56'],
    'USD-GB' => ['USD', 'en-GB', 'US$1,234.56'],
    'USD-FR' => ['USD', 'fr-FR', "1\u{202F}234,56\u{A0}\$US"],
    'EUR-US' => ['EUR', 'en-US', '€1,234.56'],
    'EUR-GB' => ['EUR', 'en-GB', '€1,234.56'],
    'EUR-FR' => ['EUR', 'fr-FR', "1\u{202F}234,56\u{A0}€"],
]);

test('formatAsCurrency strips trailing zeros when requested', function (string $currency, string $language, float $amount, bool $stripZeros, string $expected) {
    Locale::switchAppLanguage($language);

    expect(Currency::formatAsCurrency($amount, $currency, stripZeros: $stripZeros))->toBe($expected);
})->with([
    'USD-US' => ['USD', 'en-US', 1234.56, true, '$1,234.56'],
    'USD-US-strip' => ['USD', 'en-US', 1234.00, true, '$1,234'],
    'USD-US-no-strip' => ['USD', 'en-US', 1234.00, false, '$1,234.00'],
    'USD-GB' => ['USD', 'en-GB', 1234.56, true, 'US$1,234.56'],
    'USD-GB-strip' => ['USD', 'en-GB', 1234.0, true, 'US$1,234'],
    'USD-GB-no-strip' => ['USD', 'en-GB', 1234.0, false, 'US$1,234.00'],
    'USD-FR' => ['USD', 'fr-FR', 1234.56, true, "1\u{202F}234,56\u{A0}\$US"],
    'USD-FR-strip' => ['USD', 'fr-FR', 1234.00, true, "1\u{202F}234\u{A0}\$US"],
    'USD-FR-no-strip' => ['USD', 'fr-FR', 1234.00, false, "1\u{202F}234,00\u{A0}\$US"],
    'EUR-US' => ['EUR', 'en-US', 1234.56, true, '€1,234.56'],
    'EUR-US-strip' => ['EUR', 'en-US', 1234.00, true, '€1,234'],
    'EUR-US-no-strip' => ['EUR', 'en-US', 1234.00, false, '€1,234.00'],
    'EUR-FR' => ['EUR', 'fr-FR', 1234.56, true, "1\u{202F}234,56\u{A0}€"],
    'EUR-FR-strip' => ['EUR', 'fr-FR', 1234.00, true, "1\u{202F}234\u{A0}€"],
    'EUR-FR-no-strip' => ['EUR', 'fr-FR', 1234.00, false, "1\u{202F}234,00\u{A0}€"],
]);

test('formatAsCurrency formats negative amounts', function (string $currency, string $language, string $expected) {
    Locale::switchAppLanguage($language);

    expect(Currency::formatAsCurrency(-1234.56, $currency))->toBe($expected);
})->with([
    'USD-US' => ['USD', 'en-US', '-$1,234.56'],
    'USD-GB' => ['USD', 'en-GB', '-US$1,234.56'],
    'USD-FR' => ['USD', 'fr-FR', "-1\u{202F}234,56\u{A0}\$US"],
    'EUR-US' => ['EUR', 'en-US', '-€1,234.56'],
    'EUR-GB' => ['EUR', 'en-GB', '-€1,234.56'],
    'EUR-FR' => ['EUR', 'fr-FR', "-1\u{202F}234,56\u{A0}€"],
    'CHF-DE-CH' => ['CHF', 'de-CH', "CHF-1\u{2019}234.56"],
]);
