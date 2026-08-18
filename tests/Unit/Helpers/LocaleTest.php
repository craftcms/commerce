<?php

declare(strict_types=1);

use CraftCms\Commerce\Email\Models\Email;
use CraftCms\Commerce\Email\Records\Email as EmailRecord;
use CraftCms\Commerce\Helpers\Locale;
use CraftCms\Commerce\Pdf\Models\Pdf;
use CraftCms\Commerce\Pdf\Records\Pdf as PdfRecord;

test('switchAppLanguage switches the app language', function () {
    Locale::switchAppLanguage('nl');

    expect(Craft::$app->language)->toBe('nl');
});

test('Pdf::getRenderLanguage() throws without an order when language is order-language', function () {
    $pdf = new Pdf();
    $pdf->language = PdfRecord::LOCALE_ORDER_LANGUAGE;

    expect(fn() => $pdf->getRenderLanguage())->toThrow(InvalidArgumentException::class);
});

test('Email::getRenderLanguage() throws without an order when language is order-language', function () {
    $email = new Email();
    $email->language = EmailRecord::LOCALE_ORDER_LANGUAGE;

    expect(fn() => $email->getRenderLanguage())->toThrow(InvalidArgumentException::class);
});
