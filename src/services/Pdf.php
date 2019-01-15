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
    // Constants
    // =========================================================================

    /**
     * @event PdfEvent The event that is triggered before a PDF is rendered
     * Event handlers can override Commerce's PDF generation by setting [[PdfEvent::pdf]] to a custom-rendered PDF.
     */
    const EVENT_BEFORE_RENDER_PDF = 'beforeRenderPdf';

    /**
     * @event PdfEvent The event that is triggered after a PDF is rendered
     */
    const EVENT_AFTER_RENDER_PDF = 'afterRenderPdf';

    // Public Methods
    // =========================================================================

    /**
     * Returns a rendered PDF object for the order.
     *
     * @param Order $order
     * @param string $option
     * @param string $templatePath
     * @return string
     * @throws Exception if no template or order found.
     */
    public function renderPdfForOrder(Order $order, $option = '', $templatePath = null): string
    {
        if (null === $templatePath) {
            $templatePath = Plugin::getInstance()->getSettings()->orderPdfPath;
        }

        // Trigger a 'beforeRenderPdf' event
        $event = new PdfEvent([
            'order' => $order,
            'option' => $option,
            'template' => $templatePath,
        ]);
        $this->trigger(self::EVENT_BEFORE_RENDER_PDF, $event);

        if ($event->pdf !== null) {
            return $event->pdf;
        }

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
            $html = $view->renderTemplate($templatePath, compact('order', 'option'));
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
        $event = new PdfEvent([
            'order' => $order,
            'option' => $option,
            'template' => $templatePath,
            'pdf' => $dompdf->output(),
        ]);
        $this->trigger(self::EVENT_AFTER_RENDER_PDF, $event);

        return $event->pdf;
    }
}
