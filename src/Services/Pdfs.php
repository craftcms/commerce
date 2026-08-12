<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Services;

use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use craft\commerce\records\Pdf as PdfRecord;
use craft\events\ConfigEvent;
use craft\helpers\Db as CraftDb;
use craft\helpers\FileHelper;
use craft\web\View;
use CraftCms\Cms\Support\File;
use CraftCms\Cms\Support\Url;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Helpers\Locale;
use CraftCms\Commerce\Helpers\ProjectConfigData;
use CraftCms\Commerce\Pdf\Events\PdfEvent;
use CraftCms\Commerce\Pdf\Events\PdfRenderEvent;
use CraftCms\Commerce\Pdf\Events\PdfRenderOptionsEvent;
use CraftCms\Commerce\Pdf\Models\Pdf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use yii\base\ErrorException;
use function CraftCms\Cms\t;

#[Singleton]
class Pdfs
{
    public const string EVENT_BEFORE_SAVE_PDF = 'beforeSavePdf';

    public const string EVENT_AFTER_SAVE_PDF = 'afterSavePdf';

    public const string EVENT_BEFORE_RENDER_PDF = 'beforeRenderPdf';

    public const string EVENT_AFTER_RENDER_PDF = 'afterRenderPdf';

    public const string EVENT_MODIFY_RENDER_OPTIONS = 'modifyRenderOptions';

    public const string EVENT_BEFORE_DELETE_PDF = 'beforeDeletePdf';

    public const string CONFIG_PDFS_KEY = 'commerce.pdfs';

    /**
     * @var array<int, Collection<int, Pdf>>|null
     */
    private ?array $allPdfs = null;

    /**
     * @return Collection<int, Pdf>
     */
    public function getAllPdfs(?int $storeId = null): Collection
    {
        $storeId ??= Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->allPdfs === null || !isset($this->allPdfs[$storeId])) {
            $results = $this->query()->where('storeId', $storeId)->get();

            $this->allPdfs ??= [];

            foreach ($results as $result) {
                $pdf = new Pdf((array)$result);

                $this->allPdfs[$pdf->storeId] ??= collect();
                $this->allPdfs[$pdf->storeId]->push($pdf);
            }
        }

        return $this->allPdfs[$storeId] ?? collect();
    }

    public function getHasEnabledPdf(?int $storeId = null): bool
    {
        return $this->getAllPdfs($storeId)->contains('enabled', true);
    }

    /**
     * @return Collection<int, Pdf>
     */
    public function getAllEnabledPdfs(?int $storeId = null): Collection
    {
        return $this->getAllPdfs($storeId)->where('enabled', true);
    }

    public function getDefaultPdf(?int $storeId = null): ?Pdf
    {
        return $this->getAllPdfs($storeId)->firstWhere('isDefault', true);
    }

    public function getPdfByHandle(string $handle, ?int $storeId = null): ?Pdf
    {
        return $this->getAllPdfs($storeId)->firstWhere('handle', $handle);
    }

    /**
     * Get an PDF by its ID.
     */
    public function getPdfById(int $id, ?int $storeId = null): ?Pdf
    {
        return $this->getAllPdfs($storeId)->firstWhere('id', $id);
    }

    /**
     * Save an PDF.
     */
    public function savePdf(Pdf $pdf, bool $runValidation = true): bool
    {
        $isNewPdf = !(bool)$pdf->id;

        // Raise 'beforeSavePdf' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPdfs()->hasEventHandlers(self::EVENT_BEFORE_SAVE_PDF)) {
            $beforeEvent = new PdfEvent(
                pdf: $pdf,
                isNew: $isNewPdf,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPdfs()->trigger(self::EVENT_BEFORE_SAVE_PDF, $beforeEvent);
        }

        if ($runValidation && !$pdf->validate()) {
            Log::info('Pdf not saved due to validation error(s).');
            return false;
        }

        if ($isNewPdf) {
            $pdf->uid = Str::uuid()->toString();
        }

        $configPath = self::CONFIG_PDFS_KEY . '.' . $pdf->uid;
        $configData = $pdf->getConfig();
        \Craft::$app->getProjectConfig()->set($configPath, $configData);

        if ($isNewPdf) {
            $pdf->id = CraftDb::idByUid(Table::PDFS, $pdf->uid);
        }

        return true;
    }

    /**
     * Handle PDF status change.
     */
    public function handleChangedPdf(ConfigEvent $event): void
    {
        ProjectConfigData::ensureAllStoresProcessed();

        $pdfUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = \Craft::$app->getDb()->beginTransaction();
        try {
            $pdfRecord = $this->getPdfRecord($pdfUid);
            $isNewPdf = $pdfRecord->getIsNewRecord();
            $store = Plugin::getInstance()->getStores()->getStoreByUid($data['store']);

            $pdfRecord->storeId = $store->id;
            $pdfRecord->name = $data['name'];
            $pdfRecord->handle = $data['handle'];
            $pdfRecord->description = $data['description'];
            $pdfRecord->templatePath = $data['templatePath'] ?? '';
            $pdfRecord->fileNameFormat = $data['fileNameFormat'] ?? '';
            $pdfRecord->enabled = $data['enabled'];
            $pdfRecord->sortOrder = $data['sortOrder'];
            $pdfRecord->isDefault = $data['isDefault'];
            $pdfRecord->language = $data['language'] ?? PdfRecord::LOCALE_ORDER_LANGUAGE;
            $pdfRecord->paperOrientation = $data['paperOrientation'] ?? PdfRecord::PAPER_ORIENTATION_PORTRAIT;
            $pdfRecord->paperSize = $data['paperSize'] ?? 'letter';
            $pdfRecord->linkExpiry = $data['linkExpiry'] ?? 86400;

            $pdfRecord->uid = $pdfUid;

            $pdfRecord->save(false);

            if ($pdfRecord->isDefault) {
                PdfRecord::updateAll(['isDefault' => false], ['and',
                    ['not', ['id' => $pdfRecord->id]],
                    ['storeId' => $pdfRecord->storeId],
                ]);
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Raise 'afterSavePdf' event
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPdfs()->hasEventHandlers(self::EVENT_AFTER_SAVE_PDF)) {
            $afterEvent = new PdfEvent(
                pdf: $this->getPdfById($pdfRecord->id, $pdfRecord->storeId),
                isNew: $isNewPdf,
            );
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPdfs()->trigger(self::EVENT_AFTER_SAVE_PDF, $afterEvent);
        }

        $this->allPdfs = null; // clear cache
    }

    /**
     * Delete an PDF by its ID.
     */
    public function deletePdfById(int $id): bool
    {
        $pdf = PdfRecord::findOne($id);

        if ($pdf) {
            // Raise 'beforeDeletePdf' event
            // TODO: migrate event firing to Laravel once event system is bridged
            /** @phpstan-ignore-next-line */
            if (Plugin::getInstance()->getPdfs()->hasEventHandlers(self::EVENT_BEFORE_DELETE_PDF)) {
                $event = new PdfEvent(
                    pdf: $this->getPdfById($pdf->id, $pdf->storeId),
                );
                /** @phpstan-ignore-next-line */
                Plugin::getInstance()->getPdfs()->trigger(self::EVENT_BEFORE_DELETE_PDF, $event);
            }
            \Craft::$app->getProjectConfig()->remove(self::CONFIG_PDFS_KEY . '.' . $pdf->uid);
        }

        return true;
    }

    /**
     * Handle email getting deleted.
     *
     * @throws Throwable
     */
    public function handleDeletedPdf(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $pdfRecord = $this->getPdfRecord($uid);

        if (!$pdfRecord->id) {
            return;
        }

        $pdfRecord->delete();
    }

    /**
     * @param int[] $ids
     */
    public function reorderPdfs(array $ids): bool
    {
        foreach ($ids as $index => $id) {
            if ($pdf = $this->getPdfById($id)) {
                $pdf->sortOrder = $index + 1;
                $this->savePdf($pdf, false);
            }
        }

        $this->allPdfs = null; // clear cache

        return true;
    }

    /**
     * Returns a token-based URL for downloading an order's PDF.
     *
     * This URL is compatible with the DownloadsController::actionPdf() method
     * and includes a secure token for anonymous access.
     */
    public function getPdfUrl(Order $order, ?string $option = null, ?string $pdfHandle = null, bool $inline = false): string
    {
        // Load the PDF to get its link expiry setting
        if ($pdfHandle) {
            $pdf = $this->getPdfByHandle($pdfHandle);
        } else {
            $pdf = $this->getDefaultPdf();
        }

        if (!$pdf) {
            throw new \InvalidArgumentException('Can not find a PDF to generate URL.');
        }

        $expiryDate = new \DateTime()->add(new \DateInterval('PT' . $pdf->linkExpiry . 'S'));

        $token = \Craft::$app->getTokens()->createToken(
            ['commerce/downloads/pdf', ['orderNumber' => $order->number]],
            null,
            $expiryDate
        );

        // Build the URL parameters
        $params = [
            'number' => $order->number,
            'code' => $token,
        ];

        if ($pdfHandle !== null) {
            $params['pdfHandle'] = $pdfHandle;
        }

        if ($option) {
            $params['option'] = $option;
        }

        if ($inline) {
            $params['inline'] = true;
        }

        $request = \Craft::$app->getRequest();
        $isCpRequest = $request->getIsCpRequest();
        $request->setIsCpRequest(false);
        $url = Url::actionUrl('commerce/downloads/pdf', $params);
        $request->setIsCpRequest($isCpRequest);

        return $url;
    }

    /**
     * Returns a rendered PDF object for the order.
     */
    public function renderPdfForOrder(Order $order, string $option = '', ?string $templatePath = null, array $variables = [], ?Pdf $pdf = null): string
    {
        if ($pdf instanceof Pdf) {
            $templatePath = $pdf->templatePath;
        }

        if (!$templatePath) {
            $templatePath = Plugin::getInstance()->getPdfs()->getDefaultPdf()->templatePath;
        }

        // Raise 'beforeRenderPdf' event
        $event = new PdfRenderEvent(
            order: $order,
            option: $option,
            template: $templatePath,
            variables: $variables,
            sourcePdf: $pdf,
        );

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        Plugin::getInstance()->getPdfs()->trigger(self::EVENT_BEFORE_RENDER_PDF, $event);

        if ($event->pdf !== null) {
            return $event->pdf;
        }

        $variables = $event->variables;
        $variables['order'] = $event->order;
        $variables['option'] = $event->option;

        // Set Craft to the site template mode
        $view = \Craft::$app->getView();
        $originalLanguage = \Craft::$app->language;
        $originalFormattingLanguage = \Craft::$app->formattingLocale;
        $pdfLanguage = $pdf?->getRenderLanguage($order) ?? $originalLanguage;

        Locale::switchAppLanguage($pdfLanguage);

        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        if (!$event->template || !$view->doesTemplateExist($event->template)) {
            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);
            Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);

            throw new \Exception('PDF template file does not exist.');
        }

        try {
            $html = $view->renderTemplate($event->template, $variables);
        } catch (\Exception $e) {
            Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
            // Set the pdf html to the render error.
            \Craft::error('Order PDF render error. Order number: ' . $order->getShortNumber() . '. ' . $e->getMessage());
            \Craft::$app->getErrorHandler()->logException($e);
            $html = t('An error occurred while generating this PDF.', category: 'commerce');
        }

        Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage->id);
        // Restore the original template mode
        $view->setTemplateMode($oldTemplateMode);

        // Set the config options
        $pathService = \Craft::$app->getPath();
        $dompdfTempDir = $pathService->getTempPath() . DIRECTORY_SEPARATOR . 'commerce_dompdf';
        $dompdfFontCache = $pathService->getCachePath() . DIRECTORY_SEPARATOR . 'commerce_dompdf';
        $dompdfLogFile = $pathService->getLogPath() . DIRECTORY_SEPARATOR . 'commerce_dompdf.htm';

        // Ensure directories are created
        File::makeDirectory($dompdfTempDir);
        File::makeDirectory($dompdfFontCache);

        if (!FileHelper::isWritable($dompdfLogFile)) {
            throw new ErrorException("Unable to write to file: $dompdfLogFile");
        }

        if (!FileHelper::isWritable($dompdfFontCache)) {
            throw new ErrorException("Unable to write to folder: $dompdfFontCache");
        }

        if (!FileHelper::isWritable($dompdfTempDir)) {
            throw new ErrorException("Unable to write to folder: $dompdfTempDir");
        }

        $isRemoteEnabled = Plugin::getInstance()->getSettings()->pdfAllowRemoteImages;

        $options = new Options();
        $options->setTempDir($dompdfTempDir);
        $options->setFontCache($dompdfFontCache);
        $options->setLogOutputFile($dompdfLogFile);
        $options->setIsRemoteEnabled($isRemoteEnabled);

        if ($pdf instanceof Pdf) {
            $options->setDefaultPaperOrientation($pdf->paperOrientation);
            $options->setDefaultPaperSize($pdf->paperSize);
        }

        $renderOptionsEvent = new PdfRenderOptionsEvent(
            options: $options,
        );

        // Set additional render options
        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        if (Plugin::getInstance()->getPdfs()->hasEventHandlers(self::EVENT_MODIFY_RENDER_OPTIONS)) {
            /** @phpstan-ignore-next-line */
            Plugin::getInstance()->getPdfs()->trigger(self::EVENT_MODIFY_RENDER_OPTIONS, $renderOptionsEvent);
        }

        // Create and render the PDF
        $dompdf = new Dompdf($renderOptionsEvent->options);
        $dompdf->loadHtml($html);
        $dompdf->render();

        // Raise 'afterRenderPdf' event
        $afterEvent = new PdfRenderEvent(
            order: $event->order,
            option: $event->option,
            template: $event->template,
            variables: $variables,
            pdf: $dompdf->output(),
            sourcePdf: $pdf,
        );

        // TODO: migrate event firing to Laravel once event system is bridged
        /** @phpstan-ignore-next-line */
        Plugin::getInstance()->getPdfs()->trigger(self::EVENT_AFTER_RENDER_PDF, $afterEvent);

        return $afterEvent->pdf;
    }

    /**
     * Gets an PDF record by uid.
     */
    private function getPdfRecord(string $uid): PdfRecord
    {
        if ($pdf = PdfRecord::findOne(['uid' => $uid])) {
            return $pdf;
        }

        return new PdfRecord();
    }

    private function query(): Builder
    {
        $query = DB::table(Table::PDFS)
            ->select([
                'description',
                'enabled',
                'fileNameFormat',
                'handle',
                'id',
                'isDefault',
                'language',
                'name',
                'paperOrientation',
                'paperSize',
                'sortOrder',
                'storeId',
                'templatePath',
                'uid',
            ])
            ->orderBy('name')
            ->orderBy('sortOrder');

        // TODO: Remove this hasColumn check in Commerce 6.0 once the schema guarantees the linkExpiry column on the pdfs table
        if (Schema::hasColumn(Table::PDFS, 'linkExpiry')) {
            $query->addSelect('linkExpiry');
        }

        return $query;
    }
}
