<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\LineItem\Data\LineItem;

// NOTE: price rounding itself (setPrice()/setPromotionalPrice()/getSubtotal()) is covered in
// tests/Feature/Order/LineItem/LineItemPurchasableTest.php instead of here — LineItem::getPrice()
// rounds via CraftCms\Commerce\Helpers\Currency::round(), which resolves the current store's
// currency and therefore needs a real site/store, unavailable under this suite's bare UnitTestCase.

test('setOptions accepts an array or a JSON string', function() {
    // NOTE: legacy behavior stripped emoji to shortcodes on DB drivers without mb4 support;
    // that normalization hasn't been ported yet (tracked separately as COM-633), so options
    // round-trip untouched here regardless of driver.
    $options = [
        'foo' => 'bar',
        'numFoo' => 999,
        'emoji' => '❌',
    ];

    $lineItem = new LineItem();

    $lineItem->setOptions($options);
    expect($lineItem->getOptions())->toBe($options);

    $lineItem->setOptions(json_encode($options));
    expect($lineItem->getOptions())->toBe($options);
});

test('two line items with identical options produce identical options signatures', function() {
    $options = ['Larry' => 'David'];

    $lineItem1 = new LineItem();
    $lineItem2 = new LineItem();
    $lineItem1->setOptions($options);
    $lineItem2->setOptions($options);

    expect($lineItem1->getOptionsSignature())->toBe($lineItem2->getOptionsSignature());
});

test('changing the options changes the options signature', function() {
    $lineItem = new LineItem();
    $lineItem->setOptions(['foo' => 1]);
    $signature = $lineItem->getOptionsSignature();

    $lineItem->setOptions(['foo' => 2]);

    expect($lineItem->getOptionsSignature())->not->toBe($signature);
});
