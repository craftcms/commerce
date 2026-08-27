<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Order\Elements\Order;
use craft\events\ConfigEvent;
use CraftCms\Commerce\Pdf\Data\Pdf;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Pdf\Pdfs::class)` instead.
 */
class Pdfs extends Component
{
    public const EVENT_BEFORE_SAVE_PDF = \CraftCms\Commerce\Pdf\Pdfs::EVENT_BEFORE_SAVE_PDF;

    public const EVENT_AFTER_SAVE_PDF = \CraftCms\Commerce\Pdf\Pdfs::EVENT_AFTER_SAVE_PDF;

    public const EVENT_BEFORE_RENDER_PDF = \CraftCms\Commerce\Pdf\Pdfs::EVENT_BEFORE_RENDER_PDF;

    public const EVENT_AFTER_RENDER_PDF = \CraftCms\Commerce\Pdf\Pdfs::EVENT_AFTER_RENDER_PDF;

    public const EVENT_MODIFY_RENDER_OPTIONS = \CraftCms\Commerce\Pdf\Pdfs::EVENT_MODIFY_RENDER_OPTIONS;

    public const EVENT_BEFORE_DELETE_PDF = \CraftCms\Commerce\Pdf\Pdfs::EVENT_BEFORE_DELETE_PDF;

    public const CONFIG_PDFS_KEY = \CraftCms\Commerce\Pdf\Pdfs::CONFIG_PDFS_KEY;

    /**
     * @return Collection<int, Pdf>
     */
    public function getAllPdfs(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Pdf\Pdfs::class)->getAllPdfs($storeId);
    }

    public function getHasEnabledPdf(?int $storeId = null): bool
    {
        return app(\CraftCms\Commerce\Pdf\Pdfs::class)->getHasEnabledPdf($storeId);
    }

    /**
     * @return Collection<int, Pdf>
     */
    public function getAllEnabledPdfs(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Pdf\Pdfs::class)->getAllEnabledPdfs($storeId);
    }

    public function getDefaultPdf(?int $storeId = null): ?Pdf
    {
        return app(\CraftCms\Commerce\Pdf\Pdfs::class)->getDefaultPdf($storeId);
    }

    public function getPdfByHandle(string $handle, ?int $storeId = null): ?Pdf
    {
        return app(\CraftCms\Commerce\Pdf\Pdfs::class)->getPdfByHandle($handle, $storeId);
    }

    public function getPdfById(int $id, ?int $storeId = null): ?Pdf
    {
        return app(\CraftCms\Commerce\Pdf\Pdfs::class)->getPdfById($id, $storeId);
    }

    public function savePdf(Pdf $pdf, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Pdf\Pdfs::class)->savePdf($pdf, $runValidation);
    }

    public function handleChangedPdf(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Pdf\Pdfs::class)->handleChangedPdf($event);
    }

    public function deletePdfById(int $id): bool
    {
        return app(\CraftCms\Commerce\Pdf\Pdfs::class)->deletePdfById($id);
    }

    /**
     * @throws Throwable
     */
    public function handleDeletedPdf(ConfigEvent $event): void
    {
        app(\CraftCms\Commerce\Pdf\Pdfs::class)->handleDeletedPdf($event);
    }

    /**
     * @param int[] $ids
     */
    public function reorderPdfs(array $ids): bool
    {
        return app(\CraftCms\Commerce\Pdf\Pdfs::class)->reorderPdfs($ids);
    }

    public function getPdfUrl(Order $order, ?string $option = null, ?string $pdfHandle = null, bool $inline = false): string
    {
        return app(\CraftCms\Commerce\Pdf\Pdfs::class)->getPdfUrl($order, $option, $pdfHandle, $inline);
    }

    public function renderPdfForOrder(Order $order, string $option = '', ?string $templatePath = null, array $variables = [], ?Pdf $pdf = null): string
    {
        return app(\CraftCms\Commerce\Pdf\Pdfs::class)->renderPdfForOrder($order, $option, $templatePath, $variables, $pdf);
    }
}
