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
use craft\commerce\events\PdfRenderOptionsEvent;
use craft\commerce\events\PdfSaveEvent;
use craft\commerce\models\Pdf;
use craft\commerce\Plugin;
use craft\commerce\records\Pdf as PdfRecord;
use craft\db\Query;
use craft\events\ConfigEvent;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\web\View;
use Dompdf\Dompdf;
use Dompdf\Options;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\NotSupportedException;
use yii\web\ServerErrorHttpException;

/**
 * Pdf service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Pdfs extends Component
{

    /**
     * @var Pdf[]|null
     */
    private $_allPdfs;

    /**
     * @event PdfSaveEvent The event that is triggered before an pdf is saved.
     *
     * ```php
     * use craft\commerce\events\PdfSaveEvent;
     * use craft\commerce\services\Pdfs;
     * use craft\commerce\models\Pdf;
     * use yii\base\Event;
     *
     * Event::on(
     *     Pdfs::class,
     *     Pdfs::EVENT_BEFORE_SAVE_PDF,
     *     function(PdfSaveEvent $event) {
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
    const EVENT_BEFORE_SAVE_PDF = 'beforeSavePdf';

    /**
     * @event PdfSaveEvent The event that is triggered after an PDF is saved.
     *
     * ```php
     * use craft\commerce\events\PdfSaveEvent;
     * use craft\commerce\services\Pdfs;
     * use craft\commerce\models\Pdf;
     * use yii\base\Event;
     *
     * Event::on(
     *     Pdfs::class,
     *     Pdfs::EVENT_AFTER_SAVE_PDF,
     *     function(PdfSaveEvent $event) {
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
    const EVENT_AFTER_SAVE_PDF = 'afterSavePdf';

    /**
     * @event PdfEvent The event that is triggered before an order’s PDF is rendered.
     *
     * Event handlers can customize PDF rendering by modifying several properties on the event object:
     *
     * | Property    | Value                                                                                                                     |
     * | ----------- | ------------------------------------------------------------------------------------------------------------------------- |
     * | `order`     | populated [Order](api:craft\commerce\elements\Order) model                                                                |
     * | `template`  | optional Twig template path (string) to be used for rendering                                                             |
     * | `variables` | populated with the variables availble to the template used for rendering                                                  |
     * | `option`    | optional string for the template that can be used to show different details based on context (example: `receipt`, `ajax`) |
     *
     * ```php
     * use craft\commerce\events\PdfEvent;
     * use craft\commerce\services\Pdf;
     * use yii\base\Event;
     *
     * Event::on(
     *     Pdf::class,
     *     Pdf::EVENT_BEFORE_RENDER_PDF,
     *     function(PdfEvent $event) {
     *         // Modify `$event->order`, `$event->option`, `$event->template`,
     *         // and `$event->variables` to customize what gets rendered into a PDF
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_BEFORE_RENDER_PDF = 'beforeRenderPdf';

    /**
     * @event PdfEvent The event that is triggered after an order’s PDF has been rendered.
     *
     * Event handlers can override Commerce’s PDF generation by setting the `pdf` property on the event to a custom-rendered PDF string. The event properties will be the same as those from `beforeRenderPdf`, but `pdf` will contain a rendered PDF string and is the only one for which setting a value will make any difference for the resulting PDF output.
     *
     * ```php
     * use craft\commerce\events\PdfEvent;
     * use craft\commerce\services\Pdf;
     * use yii\base\Event;
     *
     * Event::on(
     *     Pdf::class,
     *     Pdf::EVENT_AFTER_RENDER_PDF,
     *     function(PdfEvent $event) {
     *         // Add a watermark to the PDF or forward it to the accounting department
     *         // ...
     *     }
     * );
     * ```
     */
    const EVENT_AFTER_RENDER_PDF = 'afterRenderPdf';

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
    const EVENT_MODIFY_RENDER_OPTIONS = 'modifyRenderOptions';

    const CONFIG_PDFS_KEY = 'commerce.pdfs';

    /**
     * @return Pdf[]
     * @since 3.2
     */
    public function getAllPdfs()
    {
        if ($this->_allPdfs === null) {
            $pdfResults = $this->_createPdfsQuery()->all();

            $this->_allPdfs = [];

            foreach ($pdfResults as $result) {
                $this->_allPdfs[] = new Pdf($result);
            }
        }

        return $this->_allPdfs;
    }

    /**
     * @return bool
     * @since 3.2
     */
    public function getHasEnabledPdf(): bool
    {
        $pdfs = $this->getAllPdfs();
        return ArrayHelper::contains($pdfs, 'enabled', true);
    }

    /**
     * @return Pdf[]
     * @since 3.2
     */
    public function getAllEnabledPdfs(): array
    {
        $pdfs = $this->getAllPdfs();
        return ArrayHelper::where($pdfs, 'enabled', true);
    }

    /**
     * @return Pdf|null
     * @since 3.2
     */
    public function getDefaultPdf()
    {
        return ArrayHelper::firstWhere($this->getAllPdfs(), 'isDefault', true);
    }

    /**
     * @param string $handle
     * @return Pdf|null
     * @since 3.2
     */
    public function getPdfByHandle($handle)
    {
        return ArrayHelper::firstWhere($this->getAllPdfs(), 'handle', $handle);
    }

    /**
     * Get an PDF by its ID.
     *
     * @param int $id
     * @return Pdf|null
     * @since 3.2
     */
    public function getPdfById($id)
    {
        return ArrayHelper::firstWhere($this->getAllPdfs(), 'id', $id);
    }

    /**
     * Save an PDF.
     *
     * @param Pdf $pdf
     * @param bool $runValidation
     * @return bool
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
            $this->trigger(self::EVENT_BEFORE_SAVE_PDF, new PdfSaveEvent([
                'pdf' => $pdf,
                'isNew' => $isNewPdf
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
     * @param ConfigEvent $event
     * @return void
     * @throws Throwable if reasons
     * @since 3.2
     */
    public function handleChangedPdf(ConfigEvent $event)
    {
        $pdfUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            $pdfRecord = $this->_getPdfRecord($pdfUid);
            $isNewPdf = $pdfRecord->getIsNewRecord();

            $pdfRecord->name = $data['name'];
            $pdfRecord->handle = $data['handle'];
            $pdfRecord->description = $data['description'];
            $pdfRecord->templatePath = $data['templatePath'];
            $pdfRecord->fileNameFormat = $data['fileNameFormat'];
            $pdfRecord->enabled = $data['enabled'];
            $pdfRecord->sortOrder = $data['sortOrder'];
            $pdfRecord->isDefault = $data['isDefault'];
            $pdfRecord->language = $data['language'];
            $pdfRecord->uid = $pdfUid;

            $pdfRecord->save(false);

            if ($pdfRecord->isDefault) {
                PdfRecord::updateAll(['isDefault' => false], ['not', ['id' => $pdfRecord->id]]);
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Fire a 'afterSavePdf' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_PDF)) {
            $this->trigger(self::EVENT_AFTER_SAVE_PDF, new PdfSaveEvent([
                'pdf' => $this->getPdfById($pdfRecord->id),
                'isNew' => $isNewPdf
            ]));
        }

        $this->_allPdfs = null; // clear cache
    }

    /**
     * Delete an PDF by its ID.
     *
     * @param int $id
     * @return bool
     * @since 3.2
     */
    public function deletePdfById($id): bool
    {
        $pdf = PdfRecord::findOne($id);

        if ($pdf) {
            Craft::$app->getProjectConfig()->remove(self::CONFIG_PDFS_KEY . '.' . $pdf->uid);
        }

        return true;
    }

    /**
     * Handle email getting deleted.
     *
     * @param ConfigEvent $event
     * @return void
     * @since 3.2
     */
    public function handleDeletedPdf(ConfigEvent $event)
    {
        $uid = $event->tokenMatches[0];
        $pdfRecord = $this->_getPdfRecord($uid);

        if (!$pdfRecord) {
            return;
        }

        $pdfRecord->delete();
    }

    /**
     * @param array $ids
     * @return bool
     * @throws \yii\db\Exception
     * @since 3.2
     */
    public function reorderPdfs(array $ids): bool
    {
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
     * @param string $templatePath The path to the template file in the site templates folder that DOMPDF will use to render the PDF.
     * @param array $variables Variables available to the pdf html template. Available to template by the array keys.
     * @param Pdf|null $pdf The PDF you want to render. This will override the templatePath argument.
     * @return string The PDF data.
     * @throws Exception
     */
    public function renderPdfForOrder(Order $order, $option = '', $templatePath = null, $variables = [], $pdf = null): string
    {
        if ($pdf !== null && $pdf instanceof Pdf) {
            $templatePath = $pdf->templatePath;
        }

        if (!$templatePath) {
            $templatePath = Plugin::getInstance()->getPdfs()->getDefaultPdf()->templatePath;
        }

        // Trigger a 'beforeRenderPdf' event
        $event = new PdfEvent([
            'order' => $order,
            'option' => $option,
            'template' => $templatePath,
            'variables' => $variables
        ]);
        $this->trigger(self::EVENT_BEFORE_RENDER_PDF, $event);

        if ($event->pdf !== null) {
            return $event->pdf;
        }

        $variables['order'] = $event->order;
        $variables['option'] = $event->option;

        // Set Craft to the site template mode
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

        if (!$event->template || !$view->doesTemplateExist($event->template)) {
            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);

            throw new Exception('PDF template file does not exist.');
        }

        try {
            $html = $view->renderTemplate($event->template, $variables);
        } catch (\Exception $e) {
            // Set the pdf html to the render error.
            Craft::error('Order PDF render error. Order number: ' . $order->getShortNumber() . '. ' . $e->getMessage());
            Craft::$app->getErrorHandler()->logException($e);
            $html = Craft::t('commerce', 'An error occurred while generating this PDF.');
        }

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

        // Set additional rener options
        if ($this->hasEventHandlers(self::EVENT_MODIFY_RENDER_OPTIONS)) {
            $this->trigger(self::EVENT_MODIFY_RENDER_OPTIONS, new PdfRenderOptionsEvent([
                'options' => $options
            ]));
        }

        // Set the options
        $dompdf->setOptions($options);

        // Paper size and orientation
        $pdfPaperSize = Plugin::getInstance()->getSettings()->pdfPaperSize;
        $pdfPaperOrientation = Plugin::getInstance()->getSettings()->pdfPaperOrientation;
        $dompdf->setPaper($pdfPaperSize, $pdfPaperOrientation);

        $dompdf->loadHtml($html);
        $dompdf->render();

        // Trigger an 'afterRenderPdf' event
        $afterEvent = new PdfEvent([
            'order' => $event->order,
            'option' => $event->option,
            'template' => $event->template,
            'variables' => $variables,
            'pdf' => $dompdf->output(),
        ]);
        $this->trigger(self::EVENT_AFTER_RENDER_PDF, $afterEvent);

        return $afterEvent->pdf;
    }

    /**
     * Gets an PDF record by uid.
     *
     * @param string $uid
     * @return PdfRecord
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
     * @return Query
     * @since 3.2
     */
    private function _createPdfsQuery(): Query
    {
        return (new Query())
            ->select([
                'id',
                'name',
                'handle',
                'description',
                'templatePath',
                'fileNameFormat',
                'enabled',
                'sortOrder',
                'isDefault',
                'language',
                'uid',
            ])
            ->orderBy('name')
            ->from([Table::PDFS])
            ->orderBy(['sortOrder' => SORT_ASC]);
    }
}
