<?php

declare(strict_types=1);

use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Email\Models\Email as EmailRecord;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Pdf\Data\Pdf;
use CraftCms\Commerce\Pdf\Models\Pdf as PdfRecord;

test('Pdf::getRenderLanguage() resolves order-language from the given order', function() {
    $order = new Order();
    $order->orderLanguage = 'nl';

    $pdf = new Pdf();
    $pdf->language = PdfRecord::LOCALE_ORDER_LANGUAGE;

    expect($pdf->getRenderLanguage($order))->toBe('nl');
});

test('Pdf::getRenderLanguage() returns its own language when not order-language', function() {
    $order = new Order();
    $order->orderLanguage = 'nl';

    $pdf = new Pdf();
    $pdf->language = 'ph';

    expect($pdf->getRenderLanguage($order))->toBe('ph');
});

test('Email::getRenderLanguage() resolves order-language from the given order', function() {
    $order = new Order();
    $order->orderLanguage = 'nl';

    $email = new Email();
    $email->language = EmailRecord::LOCALE_ORDER_LANGUAGE;

    expect($email->getRenderLanguage($order))->toBe('nl');
});

test('Email::getRenderLanguage() returns its own language when not order-language', function() {
    $order = new Order();
    $order->orderLanguage = 'nl';

    $email = new Email();
    $email->language = 'ph';

    expect($email->getRenderLanguage($order))->toBe('ph');
});
