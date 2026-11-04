<?php

namespace craft\commerce\services;

use craft\commerce\events\PdfEvent;
use craft\commerce\events\PdfRenderEvent;
use craft\commerce\events\PdfRenderOptionsEvent;
use craft\commerce\Plugin;
use craft\events\ConfigEvent;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Pdf\Data\Pdf;
use CraftCms\Commerce\Pdf\Events\PdfDeleting;
use CraftCms\Commerce\Pdf\Events\PdfRendered;
use CraftCms\Commerce\Pdf\Events\PdfRendering;
use CraftCms\Commerce\Pdf\Events\PdfRenderOptionsResolving;

use CraftCms\Commerce\Pdf\Events\PdfSaved;
use CraftCms\Commerce\Pdf\Events\PdfSaving;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
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
        app(\CraftCms\Commerce\Pdf\Pdfs::class)->handleChangedPdf(new \CraftCms\Cms\ProjectConfig\Events\ItemUpdated($event->path, $event->oldValue, $event->newValue, $event->tokenMatches));
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
        app(\CraftCms\Commerce\Pdf\Pdfs::class)->handleDeletedPdf(new \CraftCms\Cms\ProjectConfig\Events\ItemRemoved($event->path, $event->oldValue, $event->newValue, $event->tokenMatches));
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

    public static function registerEvents(): void
    {
        Event::listen(PdfSaving::class, static function(PdfSaving $event) {
            $legacy = Plugin::getInstance()->getPdfs();
            if ($legacy->hasEventHandlers(self::EVENT_BEFORE_SAVE_PDF)) {
                $legacy->trigger(self::EVENT_BEFORE_SAVE_PDF, new PdfEvent([
                    'pdf' => $event->pdf,
                    'isNew' => $event->isNew,
                ]));
            }
        });

        Event::listen(PdfSaved::class, static function(PdfSaved $event) {
            $legacy = Plugin::getInstance()->getPdfs();
            if ($legacy->hasEventHandlers(self::EVENT_AFTER_SAVE_PDF)) {
                $legacy->trigger(self::EVENT_AFTER_SAVE_PDF, new PdfEvent([
                    'pdf' => $event->pdf,
                    'isNew' => $event->isNew,
                ]));
            }
        });

        Event::listen(PdfDeleting::class, static function(PdfDeleting $event) {
            $legacy = Plugin::getInstance()->getPdfs();
            if ($legacy->hasEventHandlers(self::EVENT_BEFORE_DELETE_PDF)) {
                $legacy->trigger(self::EVENT_BEFORE_DELETE_PDF, new PdfEvent([
                    'pdf' => $event->pdf,
                    'isNew' => $event->isNew,
                ]));
            }
        });

        Event::listen(PdfRendering::class, static function(PdfRendering $event) {
            $legacy = Plugin::getInstance()->getPdfs();
            if ($legacy->hasEventHandlers(self::EVENT_BEFORE_RENDER_PDF)) {
                $legacyEvent = new PdfRenderEvent([
                    'order' => $event->order,
                    'option' => $event->option,
                    'template' => $event->template,
                    'variables' => $event->variables,
                    'pdf' => $event->pdf,
                    'sourcePdf' => $event->sourcePdf,
                ]);
                $legacy->trigger(self::EVENT_BEFORE_RENDER_PDF, $legacyEvent);
                $event->option = $legacyEvent->option;
                $event->template = $legacyEvent->template;
                $event->variables = $legacyEvent->variables;
                $event->pdf = $legacyEvent->pdf;
            }
        });

        Event::listen(PdfRendered::class, static function(PdfRendered $event) {
            $legacy = Plugin::getInstance()->getPdfs();
            if ($legacy->hasEventHandlers(self::EVENT_AFTER_RENDER_PDF)) {
                $legacyEvent = new PdfRenderEvent([
                    'order' => $event->order,
                    'option' => $event->option,
                    'template' => $event->template,
                    'variables' => $event->variables,
                    'pdf' => $event->pdf,
                    'sourcePdf' => $event->sourcePdf,
                ]);
                $legacy->trigger(self::EVENT_AFTER_RENDER_PDF, $legacyEvent);
                $event->option = $legacyEvent->option;
                $event->template = $legacyEvent->template;
                $event->variables = $legacyEvent->variables;
                $event->pdf = $legacyEvent->pdf;
            }
        });

        Event::listen(PdfRenderOptionsResolving::class, static function(PdfRenderOptionsResolving $event) {
            $legacy = Plugin::getInstance()->getPdfs();
            if ($legacy->hasEventHandlers(self::EVENT_MODIFY_RENDER_OPTIONS)) {
                $legacyEvent = new PdfRenderOptionsEvent(['options' => $event->options]);
                $legacy->trigger(self::EVENT_MODIFY_RENDER_OPTIONS, $legacyEvent);
                $event->options = $legacyEvent->options;
            }
        });
    }
}
