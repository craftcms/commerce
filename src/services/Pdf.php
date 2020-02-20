<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\PdfEvent;
use craft\commerce\Plugin;
use craft\helpers\FileHelper;
use craft\web\View;
use Dompdf\Dompdf;
use Dompdf\Options;
use yii\base\Component;
use yii\base\Exception;

/**
 * Pdf service.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Pdf extends Component
{
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
     * Returns a rendered PDF object for the order.
     *
     * @param Order $order
     * @param string $option
     * @param string $templatePath
     * @param array $variables variables available to the pdf html template. Available to template by the array keys.
     * @return string
     * @throws Exception if no template or order found.
     */
    public function renderPdfForOrder(Order $order, $option = '', $templatePath = null, $variables = []): string
    {
        if (null === $templatePath) {
            $templatePath = Plugin::getInstance()->getSettings()->orderPdfPath;
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

        if (!$templatePath || !$view->doesTemplateExist($templatePath)) {
            // Restore the original template mode
            $view->setTemplateMode($oldTemplateMode);

            throw new Exception('PDF template file does not exist.');
        }

        try {
            $html = $view->renderTemplate($templatePath, $variables);
        } catch (\Exception $e) {
            // Set the pdf html to the render error.
            Craft::error('Order PDF render error. Order number: ' . $order->getShortNumber() . '. ' . $e->getMessage());
            Craft::$app->getErrorHandler()->logException($e);
            $html = Plugin::t('An error occurred while generating this PDF.');
        }

        // Restore the original template mode
        $view->setTemplateMode($oldTemplateMode);

        $dompdf = new Dompdf();

        // Set the config options
        $pathService = Craft::$app->getPath();
        $dompdfTempDir = $pathService->getTempPath() . DIRECTORY_SEPARATOR . 'commerce_dompdf';
        $dompdfFontCache = $pathService->getCachePath() . DIRECTORY_SEPARATOR . 'commerce_dompdf';
        $dompdfLogFile = $pathService->getLogPath() . DIRECTORY_SEPARATOR . 'commerce_dompdf.htm';

        // Should throw an error if not writable
        FileHelper::isWritable($dompdfTempDir);
        FileHelper::isWritable($dompdfLogFile);

        $isRemoteEnabled = Plugin::getInstance()->getSettings()->pdfAllowRemoteImages;

        $options = new Options();
        $options->setTempDir($dompdfTempDir);
        $options->setFontCache($dompdfFontCache);
        $options->setLogOutputFile($dompdfLogFile);
        $options->setIsRemoteEnabled($isRemoteEnabled);

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
}
