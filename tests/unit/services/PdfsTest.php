<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craftcommercetests\unit\services;

use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\models\Pdf;
use craft\commerce\Plugin;
use craft\commerce\services\Pdfs;
use craftcommercetests\fixtures\StoreFixture;
use UnitTester;

/**
 * PdfsTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 */
class PdfsTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    /**
     * @var Pdfs
     */
    protected Pdfs $service;

    /**
     * @return array
     */
    public function _fixtures(): array
    {
        return [
            'stores' => [
                'class' => StoreFixture::class,
            ],
        ];
    }

    /**
     * Guards against a PDF that only exists on a non-primary store being looked up
     * against the current (primary, in the CP) store instead of the order's own store.
     *
     * https://github.com/craftcms/commerce/issues/4370
     *
     * @return void
     */
    public function testGetPdfUrlUsesOrdersStoreNotCurrentStore(): void
    {
        $ukStore = Plugin::getInstance()->getStores()->getStoreByHandle('ukStore');
        self::assertNotNull($ukStore);
        self::assertNotEquals($ukStore->id, Plugin::getInstance()->getStores()->getCurrentStore()->id);

        $pdf = new Pdf([
            'storeId' => $ukStore->id,
            'name' => 'UK Receipt',
            'handle' => 'ukReceipt',
            'templatePath' => 'shop/pdf/order',
            'isDefault' => true,
            'enabled' => true,
        ]);
        self::assertTrue($this->service->savePdf($pdf));

        $order = new Order([
            'storeId' => $ukStore->id,
        ]);
        $order->number = 'pdfs-test-' . strtolower(bin2hex(random_bytes(10)));

        // Neither call should throw, since both should resolve the PDF against the
        // order's store rather than the current store.
        $urlByHandle = $this->service->getPdfUrl($order, null, $pdf->handle);
        self::assertStringContainsString('pdfHandle=' . $pdf->handle, $urlByHandle);

        $urlByDefault = $this->service->getPdfUrl($order);
        self::assertIsString($urlByDefault);
    }

    /**
     * @return void
     */
    public function _before(): void
    {
        parent::_before();

        $this->service = Plugin::getInstance()->getPdfs();
    }
}
