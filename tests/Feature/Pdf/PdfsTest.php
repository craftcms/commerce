<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Pdf\Data\Pdf;
use CraftCms\Commerce\Pdf\Pdfs;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\StoresFixture;

test('getPdfUrl resolves the PDF against the order\'s store, not the current store', function() {
    $fixture = StoresFixture::seed();
    expect($fixture->ukStore->id)->not->toBe(app(Stores::class)->getCurrentStore()->id);

    $pdf = new Pdf([
        'storeId' => $fixture->ukStore->id,
        'name' => 'UK Receipt',
        'handle' => 'ukReceipt',
        'templatePath' => 'shop/pdf/order',
        'isDefault' => true,
        'enabled' => true,
    ]);
    expect(app(Pdfs::class)->savePdf($pdf))->toBeTrue();

    $order = new Order(['storeId' => $fixture->ukStore->id]);
    $order->number = 'pdfs-test-' . strtolower(bin2hex(random_bytes(10)));

    expect(app(Pdfs::class)->getPdfUrl($order, null, $pdf->handle))->toContain('pdfHandle=' . $pdf->handle)
        ->and(app(Pdfs::class)->getPdfUrl($order))->toBeString();
});
