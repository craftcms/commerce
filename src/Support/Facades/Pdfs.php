<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static \Illuminate\Support\Collection getAllPdfs(int|null $storeId = null)
 * @method static bool getHasEnabledPdf(int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getAllEnabledPdfs(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Pdf\Data\Pdf|null getDefaultPdf(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Pdf\Data\Pdf|null getPdfByHandle(string $handle, int|null $storeId = null)
 * @method static \CraftCms\Commerce\Pdf\Data\Pdf|null getPdfById(int $id, int|null $storeId = null)
 * @method static bool savePdf(\CraftCms\Commerce\Pdf\Data\Pdf $pdf, bool $runValidation = true)
 * @method static void handleChangedPdf(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static bool deletePdfById(int $id)
 * @method static void handleDeletedPdf(\CraftCms\Cms\ProjectConfig\Events\ConfigEvent $event)
 * @method static bool reorderPdfs(int[] $ids)
 * @method static string getPdfUrl(\CraftCms\Commerce\Order\Elements\Order $order, string|null $option = null, string|null $pdfHandle = null, bool $inline = false)
 * @method static string renderPdfForOrder(\CraftCms\Commerce\Order\Elements\Order $order, string $option = '', string|null $templatePath = null, array $variables = [], \CraftCms\Commerce\Pdf\Data\Pdf|null $pdf = null)
 *
 * @see \CraftCms\Commerce\Pdf\Pdfs
 */
class Pdfs extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Pdf\Pdfs::class;
    }
}
