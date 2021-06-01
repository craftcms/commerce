<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\commerce\elements\Order;
use craft\commerce\models\Pdf;
use craft\commerce\Plugin;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use iio\libmergepdf\Merger;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use ZipArchive;

/**
 * Class Update Order Status
 *
 * @property null|string $triggerHtml the action’s trigger HTML
 * @property string $triggerLabel the action’s trigger label
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2
 */
class DownloadOrderPdf extends ElementAction
{
    /**
     * @inheritdoc
     */
    public static function isDownload(): bool
    {
        return true;
    }

    /**
     * @var int
     */
    public $pdfId;

    /**
     * @var bool
     */
    public $zip = false;

    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('commerce', 'Download PDF');
    }

    /**
     * @inheritdoc
     */
    public function getTriggerHtml()
    {
        $pdfs = Plugin::getInstance()->getPdfs()->getAllEnabledPdfs();
        return Craft::$app->getView()->renderTemplate('commerce/_components/elementactions/DownloadOrderPdf/trigger', [
            'pdfs' => $pdfs,
        ]);
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $pdfsService = Plugin::getInstance()->getPdfs();

        $pdfId = (int)$this->pdfId;
        $zip = (bool)$this->zip;
        $pdf = $pdfsService->getPdfById($pdfId);

        if (!$pdf) {
            throw new InvalidConfigException("Invalid PDF ID: '" . $pdfId . "'");
        }

        /** @var Order[] $orders */
        $orders = $query->all();

        if (empty($orders)) {
            return false;
        }

        $response = Craft::$app->getResponse();

        // Only one order, download single PDF
        if (count($orders) === 1 && !$zip) {
            $order = reset($orders);
            $renderedPdf = $pdfsService->renderPdfForOrder($order, '', null, [], $pdf);
            $filename = $this->_pdfFileName($pdf, $order);
            $response->sendContentAsFile($renderedPdf, $filename);
            return true;
        }

        // Download collated in single PDF file
        $merger = new Merger();
        if (!$zip) {
            foreach ($orders as $order) {
                $renderedPdf = $pdfsService->renderPdfForOrder($order, '', null, [], $pdf);
                $merger->addRaw($renderedPdf);
            }
            $mergedPdf = $merger->merge();
            $response->sendContentAsFile($mergedPdf, 'Orders.pdf');
            return true;
        }

        // If it is not collated, then it is a zip request
        $zip = new ZipArchive();
        $zipPath = Craft::$app->getPath()->getTempPath() . '/' . StringHelper::UUID() . '.zip';

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new Exception('Cannot create zip at ' . $zipPath);
        }

        foreach ($orders as $order) {
            $renderedPdf = $pdfsService->renderPdfForOrder($order, '', null, [], $pdf);
            $filename = $this->_pdfFileName($pdf, $order);
            $zip->addFromString($filename, $renderedPdf);
        }

        $zip->close();
        Craft::$app->getResponse()->sendContentAsFile(file_get_contents($zipPath), 'Orders.zip');
        FileHelper::unlink($zipPath);

        return true;
    }

    /**
     * Returns a PDF’s file name
     *
     * @param Pdf $pdf
     * @param Order $order
     */
    private function _pdfFileName(Pdf $pdf, Order $order): string
    {
        $fileName = Craft::$app->getView()->renderObjectTemplate((string)$pdf->fileNameFormat, $order);
        if (!$fileName) {
            $fileName = $pdf->handle . '-' . $order->number;
        }
        return "$fileName.pdf";
    }
}
