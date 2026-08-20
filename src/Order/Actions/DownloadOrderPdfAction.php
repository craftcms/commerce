<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Actions;

use CraftCms\Cms\Element\Actions\ElementAction;
use CraftCms\Cms\Element\Queries\Contracts\ElementQueryInterface;
use CraftCms\Cms\Support\Facades\HtmlStack;
use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Pdf\Models\Pdf;
use CraftCms\Commerce\Pdf\Pdfs;
use iio\libmergepdf\Merger;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

use function CraftCms\Cms\renderSandboxedObjectTemplate;
use function CraftCms\Cms\t;
use function CraftCms\Cms\template;

class DownloadOrderPdfAction extends ElementAction
{
    public const TYPE_ZIP_ARCHIVE = 'zipArchive';
    public const TYPE_PDF_COLLATED = 'pdfCollated';

    #[\Override]
    public static function isDownload(): bool
    {
        return true;
    }

    public ?int $pdfId = null;

    public string $downloadType = self::TYPE_PDF_COLLATED;

    public ?int $storeId = null;

    public function getTriggerLabel(): string
    {
        return t('Download PDF', category: 'commerce');
    }

    public function getTriggerHtml(): ?string
    {
        if ($this->storeId === null) {
            return '';
        }

        $allPdfs = app(Pdfs::class)->getAllEnabledPdfs($this->storeId);

        $pdfs = [];
        foreach ($allPdfs as $pdf) {
            $pdfs[] = ['label' => t($pdf->name, category: 'site'), 'value' => $pdf->id];
        }
        $pdfOptions = Json::encode($pdfs);

        $typeOptions = Json::encode([
            ['label' => t('ZIP file', category: 'commerce'), 'value' => self::TYPE_ZIP_ARCHIVE],
            ['label' => t('Collated PDF', category: 'commerce'), 'value' => self::TYPE_PDF_COLLATED],
        ]);

        $action = Json::encode(static::class);

        if (count($allPdfs) > 0) {
            $js = <<<JS
(() => {
    new Craft.Commerce.DownloadOrderPdfAction($('#download-order-pdf'), $pdfOptions, $typeOptions, $action);
})();
JS;
            HtmlStack::js($js);
            return template('commerce/_components/elementactions/DownloadOrderPdf/trigger', [], TemplateMode::Cp);
        }

        return '';
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        if ($this->storeId === null) {
            throw new RuntimeException('Invalid store ID');
        }

        $pdfsService = app(Pdfs::class);

        $pdfId = $this->pdfId;
        if ($pdfId === null) {
            throw new RuntimeException('Invalid PDF ID');
        }

        $pdf = $pdfsService->getPdfById($pdfId, $this->storeId);

        if (!$pdf) {
            throw new RuntimeException("Invalid PDF ID: '" . $pdfId . "'");
        }

        /** @var Order[] $orders */
        $orders = $query->all();

        if (empty($orders)) {
            return false;
        }

        // Only one order, download single PDF
        if (count($orders) === 1 && $this->downloadType === self::TYPE_PDF_COLLATED) {
            $order = reset($orders);
            $renderedPdf = $pdfsService->renderPdfForOrder($order, '', null, [], $pdf);
            $filename = $this->pdfFileName($pdf, $order);
            $this->setResponse($this->fileResponse($renderedPdf, $filename, 'application/pdf'));
            return true;
        }

        // Download collated in single PDF file
        if ($this->downloadType === self::TYPE_PDF_COLLATED) {
            $merger = new Merger();
            foreach ($orders as $order) {
                $renderedPdf = $pdfsService->renderPdfForOrder($order, '', null, [], $pdf);
                $merger->addRaw($renderedPdf);
            }
            $mergedPdf = $merger->merge();
            $this->setResponse($this->fileResponse($mergedPdf, 'Orders.pdf', 'application/pdf'));
            return true;
        }

        // If it is not collated, then it is a zip request
        $zip = new ZipArchive();
        $zipPath = Path::temp() . '/' . (string)Str::uuid() . '.zip';

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException('Cannot create zip at ' . $zipPath);
        }

        foreach ($orders as $order) {
            $renderedPdf = $pdfsService->renderPdfForOrder($order, '', null, [], $pdf);
            $filename = $this->pdfFileName($pdf, $order);
            $zip->addFromString($filename, $renderedPdf);
        }

        $zip->close();
        $this->setResponse($this->fileResponse((string)file_get_contents($zipPath), 'Orders.zip', 'application/zip'));
        unlink($zipPath);

        return true;
    }

    private function fileResponse(string $content, string $filename, string $contentType): Response
    {
        return new Response(
            content: $content,
            status: 200,
            headers: [
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
                'Content-Type' => $contentType,
            ],
        );
    }

    /**
     * Returns a PDF's file name
     */
    private function pdfFileName(Pdf $pdf, Order $order): string
    {
        $fileName = renderSandboxedObjectTemplate($pdf->fileNameFormat, $order);
        if (!$fileName) {
            $fileName = $pdf->handle . '-' . $order->number;
        }

        return $fileName . '.pdf';
    }
}
