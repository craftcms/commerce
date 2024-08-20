<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\db\Table;
use craft\commerce\elements\Order;
use craft\commerce\events\PdfEvent;
use craft\commerce\events\PdfRenderEvent;
use craft\commerce\events\PdfRenderOptionsEvent;
use craft\commerce\helpers\Locale;
use craft\commerce\helpers\ProjectConfigData;
use craft\commerce\models\Pdf;
use craft\commerce\Plugin;
use craft\commerce\records\Pdf as PdfRecord;
use craft\db\Query;
use craft\errors\SiteNotFoundException;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\web\View;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\db\StaleObjectException;
use yii\web\ServerErrorHttpException;

/**
 * Pdf service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 *
 * @property-read null|Pdf $defaultPdf
 * @property-read Pdf[] $allEnabledPdfs
 * @property-read bool $hasEnabledPdf
 * @property-read null|Pdf[] $allPdfs
 */
class Pdfs extends Component
{
    /**
     * @var Pdf[]|null
     */
    private ?array $_allPdfs = null;

    /**
     * @event PdfEvent The event that is triggered before an pdf is saved.
     *
     * ```php
     * use craft\commerce\events\PdfEvent;
     * use craft\commerce\services\Pdfs;
     * use craft\commerce\models\Pdf;
     * use yii\base\Event;
     *
     * Event::on(
     *     Pdfs::class,
     *     Pdfs::EVENT_BEFORE_SAVE_PDF,
     *     function(PdfEvent $event) {
     *         // @var Pdf $pdf
     *         $pdf = $event->pdf;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_BEFORE_SAVE_PDF = 'beforeSavePdf';

    /**
     * @event PdfEvent The event that is triggered after an PDF is saved.
     *
     * ```php
     * use craft\commerce\events\PdfEvent;
     * use craft\commerce\services\Pdfs;
     * use craft\commerce\models\Pdf;
     * use yii\base\Event;
     *
     * Event::on(
     *     Pdfs::class,
     *     Pdfs::EVENT_AFTER_SAVE_PDF,
     *     function(PdfEvent $event) {
     *         // @var Pdf $pdf
     *         $pdf = $event->pdf;
     *         // @var bool $isNew
     *         $isNew = $event->isNew;
     *
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_AFTER_SAVE_PDF = 'afterSavePdf';

    /**
     * @event PdfRenderEvent The event that is triggered before an order’s PDF is rendered.
     *
     * Event handlers can customize PDF rendering by modifying several properties on the event object:
     *
     * | Property    | Value                                                                                                                     |
     * | ----------- | ------------------------------------------------------------------------------------------------------------------------- |
     * | `order`     | populated [Order](api:craft\commerce\elements\Order) model                                                                |
     * | `template`  | optional Twig template path (string) to be used for rendering                                                             |
     * | `variables` | populated with the variables available to the template used for rendering                                                  |
     * | `option`    | optional string for the template that can be used to show different details based on context (example: `receipt`, `ajax`) |
     *
     * ```php
     * use craft\commerce\events\PdfRenderEvent;
     * use craft\commerce\services\Pdf;
     * use yii\base\Event;
     *
     * Event::on(
     *     Pdf::class,
     *     Pdf::EVENT_BEFORE_RENDER_PDF,
     *     function(PdfRenderEvent $event) {
     *         // Modify `$event->order`, `$event->option`, `$event->template`,
     *         // and `$event->variables` to customize what gets rendered into a PDF
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_BEFORE_RENDER_PDF = 'beforeRenderPdf';

    /**
     * @event PdfRenderEvent The event that is triggered after an order’s PDF has been rendered.
     *
     * Event handlers can override Commerce’s PDF generation by setting the `pdf` property on the event to a custom-rendered PDF string. The event properties will be the same as those from `beforeRenderPdf`, but `pdf` will contain a rendered PDF string and is the only one for which setting a value will make any difference for the resulting PDF output.
     *
     * ```php
     * use craft\commerce\events\PdfRenderEvent;
     * use craft\commerce\services\Pdf;
     * use yii\base\Event;
     *
     * Event::on(
     *     Pdf::class,
     *     Pdf::EVENT_AFTER_RENDER_PDF,
     *     function(PdfRenderEvent $event) {
     *         // Add a watermark to the PDF or forward it to the accounting department
     *         // ...
     *     }
     * );
     * ```
     */
    public const EVENT_AFTER_RENDER_PDF = 'afterRenderPdf';

    /**
     * @event PdfRenderOptionsEvent The event that allows additional setting of pdf render options.
     * @since 3.2.10
     *
     * ```php
     * use craft\commerce\events\PdfRenderOptionsEvent;
     * use craft\commerce\services\Pdfs;
     * use yii\base\Event;
     *
     * Event::on(
     *     Pdfs::class,
     *    Pdfs::EVENT_MODIFY_RENDER_OPTIONS,
     *    function (PdfRenderOptionsEvent $event) {
     *        $storagePath = Craft::$app->getPath()->getStoragePath();
     *
     *        // E.g. of setting additional render options.
     *        $event->options->setChroot($storagePath);
     *    }
     * );
     *```
     */
    public const EVENT_MODIFY_RENDER_OPTIONS = 'modifyRenderOptions';

    /**
     * @event PdfEvent The event that is triggered before a pdf is deleted.
     *
     * ```php
     * use craft\commerce\events\PdfEvent;
     * use craft\commerce\services\Pdfs;
     * use craft\commerce\models\Pdf;
     * use yii\base\Event;
     *
     * Event::on(
     *     Pdfs::class,
     *     Pdfs::EVENT_BEFORE_DELETE_PDF,
     *     function(PdfEvent $event) {
     *         // @var Pdf $pdf
     *         $pdf = $event->pdf;
     *
     *         // ...
     *     }
     * );
     * ```
     *
     * @since 4.0.0
     */
    public const EVENT_BEFORE_DELETE_PDF = 'beforeDeletePdf';

    public const CONFIG_PDFS_KEY = 'commerce.pdfs';

    /**
     * @param int|null $storeId
     * @return Collection<Pdf>
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     * @since 3.2
     */
    public function getAllPdfs(?int $storeId = null): Collection
    {
        $storeId = $storeId ?? Plugin::getInstance()->getStores()->getCurrentStore()->id;

        if ($this->_allPdfs === null || !isset($this->_allPdfs[$storeId])) {
            $results = $this->_createPdfsQuery()
                ->where(['storeId' => $storeId])
                ->all();

            // Start with a blank slate if it isn't memoized, or we're fetching all shipping categories
            if ($this->_allPdfs === null) {
                $this->_allPdfs = [];
            }

            foreach ($results as $result) {
                $pdf = Craft::createObject([
                    'class' => Pdf::class,
                    'attributes' => $result,
                ]);

                if (!isset($this->_allPdfs[$pdf->storeId])) {
                    $this->_allPdfs[$pdf->storeId] = collect();
                }

                $this->_allPdfs[$pdf->storeId]->push($pdf);
            }
        }

        return $this->_allPdfs[$storeId] ?? collect();
    }

    /**
     * @since 3.2
     */
    public function getHasEnabledPdf(?int $storeId = null): bool
    {
        return $this->getAllPdfs($storeId)->contains('enabled', true);
    }

    /**
     * @param int|null $storeId
     * @return Collection<Pdf>
     * @since 3.2
     */
    public function getAllEnabledPdfs(?int $storeId = null): Collection
    {
        return $this->getAllPdfs($storeId)->where('enabled', true);
    }

    /**
     * @since 3.2
     */
    public function getDefaultPdf(?int $storeId = null): ?Pdf
    {
        return $this->getAllPdfs($storeId)->firstWhere('isDefault', true);
    }

    /**
     * @since 3.2
     */
    public function getPdfByHandle(string $handle, ?int $storeId = null): ?Pdf
    {
        return $this->getAllPdfs($storeId)->firstWhere('handle', $handle);
    }

    /**
     * Get an PDF by its ID.
     *
     * @since 3.2
     */
    public function getPdfById(int $id, ?int $storeId = null): ?Pdf
    {
        return $this->getAllPdfs($storeId)->firstWhere('id', $id);
    }

    /**
     * Save an PDF.
     *
     * @throws Exception
     * @throws ErrorException
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     * @since 3.2
     */
    public function savePdf(Pdf $pdf, bool $runValidation = true): bool
    {
        $isNewPdf = !(bool)$pdf->id;

        // Fire a 'beforeSavePdf' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_PDF)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_PDF, new PdfEvent([
                'pdf' => $pdf,
                'isNew' => $isNewPdf,
            ]));
        }

        if ($runValidation && !$pdf->validate()) {
            Craft::info('Pdf not saved due to validation error(s).', __METHOD__);
            return false;
        }

        if ($isNewPdf) {
            $pdf->uid = StringHelper::UUID();
        }

        $configPath = self::CONFIG_PDFS_KEY . '.' . $pdf->uid;
        $configData = $pdf->getConfig();
        Craft::$app->getProjectConfig()->set($configPath, $configData);

        if ($isNewPdf) {
            $pdf->id = Db::idByUid(Table::PDFS, $pdf->uid);
        }

        return true;
    }

    /**
     * Handle PDF status change.
     *
     * @throws \yii\db\Exception
     * @since 3.2
     */
    public function handleChangedPdf(ConfigEvent $event): void
    {
        ProjectConfigData::ensureAllStoresProcessed();

        $pdfUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $pdfRecord = $this->_getPdfRecord($pdfUid);
            $isNewPdf = $pdfRecord->getIsNewRecord();
            $store = Plugin::getInstance()->getStores()->getStoreByUid($data['store']);

            $pdfRecord->storeId = $store->id;
            $pdfRecord->name = $data['name'];
            $pdfRecord->handle = $data['handle'];
            $pdfRecord->description = $data['description'];
            $pdfRecord->templatePath = $data['templatePath'];
            $pdfRecord->fileNameFormat = $data['fileNameFormat'];
            $pdfRecord->enabled = $data['enabled'];
            $pdfRecord->sortOrder = $data['sortOrder'];
            $pdfRecord->isDefault = $data['isDefault'];
            $pdfRecord->language = $data['language'] ?? PdfRecord::LOCALE_ORDER_LANGUAGE;
            $pdfRecord->paperOrientation = $data['paperOrientation'] ?? PdfRecord::PAPER_ORIENTATION_PORTRAIT;
            $pdfRecord->paperSize = $data['paperSize'] ?? 'letter';

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

        // Fire a 'afterSavePdf' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_PDF)) {
            $this->trigger(self::EVENT_AFTER_SAVE_PDF, new PdfEvent([
                'pdf' => $this->getPdfById($pdfRecord->id, $pdfRecord->storeId),
                'isNew' => $isNewPdf,
            ]));
        }

        $this->_allPdfs = null; // clear cache
    }

    /**
     * Delete an PDF by its ID.
     *
     * @since 3.2
     */
    public function deletePdfById(int $id): bool
    {
        $pdf = PdfRecord::findOne($id);

        if ($pdf) {
            // Fire a 'beforeDeletePdf' event
            if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_PDF)) {
                $this->trigger(self::EVENT_BEFORE_DELETE_PDF, new PdfEvent([
                    'pdf' => $this->getPdfById($pdf->id, $pdf->storeId),
                ]));
            }
            Craft::$app->getProjectConfig()->remove(self::CONFIG_PDFS_KEY . '.' . $pdf->uid);
        }

        return true;
    }

    /**
     * Handle email getting deleted.
     *
     * @throws Throwable
     * @throws StaleObjectException
     * @since 3.2
     */
    public function handleDeletedPdf(ConfigEvent $event): void
    {
        $uid = $event->tokenMatches[0];
        $pdfRecord = $this->_getPdfRecord($uid);

        if (!$pdfRecord->id) {
            return;
        }

        $pdfRecord->delete();
    }

    /**
     * @throws ErrorException
     * @throws Exception
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     * @since 3.2
     */
    public function reorderPdfs(array $ids): bool
    {
        // TODO Add event
        // @TODO make reordering consistent across features
        foreach ($ids as $index => $id) {
            if ($pdf = $this->getPdfById($id)) {
                $pdf->sortOrder = $index + 1;
                $this->savePdf($pdf, false);
            }
        }

        $this->_allPdfs = null; // clear cache

        return true;
    }


    /**
     * Returns a rendered PDF object for the order.
     *
     * @param Order $order The order you want passed into the PDFs `order` variable.
     * @param string $option A string you want passed into the PDFs `option` variable.
     * @param string|null $templatePath The path to the template file in the site templates folder that DOMPDF will use to render the PDF.
     * @param array $variables Variables available to the pdf html template. Available to template by the array keys.
     * @param Pdf|null $pdf The PDF you want to render. This will override the templatePath argument.
     * @return string The PDF data.
     * @throws Exception
     */
    public function renderPdfForOrder(Order $order, string $option = '', string $templatePath = null, array $variables = [], Pdf $pdf = null): string
    {
        if ($pdf instanceof Pdf) {
            $templatePath = $pdf->templatePath;
        }

        if (!$templatePath) {
            $templatePath = Plugin::getInstance()->getPdfs()->getDefaultPdf()->templatePath;
        }

        // Trigger a 'beforeRenderPdf' event
        $event = new PdfRenderEvent([
            'order' => $order,
            'option' => $option,
            'template' => $templatePath,
            'variables' => $variables,
            'sourcePdf' => $pdf,
        ]);
        $this->trigger(self::EVENT_BEFORE_RENDER_PDF, $event);

        if ($event->pdf !== null) {
            return $event->pdf;
        }

        $variables = $event->variables;
        $variables['order'] = $event->order;
        $variables['option'] = $event->option;

        // Set Craft to the site template mode
        $view = Craft::$app->getView();
        $originalLanguage = Craft::$app->language;
        $originalFormattingLanguage = Craft::$app->formattingLocale;
        $pdfLanguage = $pdf?->getRenderLanguage($order) ?? $originalLanguage;

        // TODO add event
        Locale::switchAppLanguage($pdfLanguage);

        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        if (!$event->template || !$view->doesTemplateExist($event->template)) {
            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);
            Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage);

            throw new Exception('PDF template file does not exist.');
        }

        try {
            // TODO Add event
            $html = $view->renderTemplate($event->template, $variables);
        } catch (\Exception $e) {
            Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage);
            // Set the pdf html to the render error.
            Craft::error('Order PDF render error. Order number: ' . $order->getShortNumber() . '. ' . $e->getMessage());
            Craft::$app->getErrorHandler()->logException($e);
            $html = Craft::t('commerce', 'An error occurred while generating this PDF.');
        }

        Locale::switchAppLanguage($originalLanguage, $originalFormattingLanguage);
        // Restore the original template mode
        $view->setTemplateMode($oldTemplateMode);

        $dompdf = new Dompdf();

        // Set the config options
        $pathService = Craft::$app->getPath();
        $dompdfTempDir = $pathService->getTempPath() . DIRECTORY_SEPARATOR . 'commerce_dompdf';
        $dompdfFontCache = $pathService->getCachePath() . DIRECTORY_SEPARATOR . 'commerce_dompdf';
        $dompdfLogFile = $pathService->getLogPath() . DIRECTORY_SEPARATOR . 'commerce_dompdf.htm';

        // Ensure directories are created
        FileHelper::createDirectory($dompdfTempDir);
        FileHelper::createDirectory($dompdfFontCache);

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
        // Set additional render options
        if ($this->hasEventHandlers(self::EVENT_MODIFY_RENDER_OPTIONS)) {
            $this->trigger(self::EVENT_MODIFY_RENDER_OPTIONS, new PdfRenderOptionsEvent([
                'options' => $options,
            ]));
        }

        // Set the options
        $dompdf->setOptions($options);


        $dompdf->setPaper($pdf->paperSize, $pdf->paperOrientation);

        $dompdf->loadHtml($html);
        $dompdf->render();

        // Trigger an 'afterRenderPdf' event
        $afterEvent = new PdfRenderEvent([
            'order' => $event->order,
            'option' => $event->option,
            'template' => $event->template,
            'variables' => $variables,
            'pdf' => $dompdf->output(),
            'sourcePdf' => $pdf,
        ]);
        $this->trigger(self::EVENT_AFTER_RENDER_PDF, $afterEvent);

        return $afterEvent->pdf;
    }

    /**
     * Gets an PDF record by uid.
     *
     * @since 3.2
     */
    private function _getPdfRecord(string $uid): PdfRecord
    {
        if ($pdf = PdfRecord::findOne(['uid' => $uid])) {
            return $pdf;
        }

        return new PdfRecord();
    }

    /**
     * Returns a Query object prepped for retrieving PDFs.
     *
     * @since 3.2
     */
    private function _createPdfsQuery(): Query
    {
        return (new Query())
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
            ->from([Table::PDFS])
            ->orderBy(['sortOrder' => SORT_ASC]);
    }
}
