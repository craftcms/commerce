<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\helpers\DebugPanel;
use craft\commerce\helpers\Locale as LocaleHelper;
use craft\commerce\models\Pdf;
use craft\commerce\models\Store;
use craft\commerce\Plugin;
use craft\commerce\records\Pdf as PdfRecord;
use craft\helpers\Json;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class Pdfs Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2
 */
class PdfsController extends BaseAdminController
{
    /**
     * @since 3.2
     */
    public function actionIndex(): Response
    {
        $pdfs = [];
        $stores = Plugin::getInstance()->getStores()->getAllStores();

        $stores->each(function(Store $store) use (&$pdfs) {
            $pdfs[$store->handle] = Plugin::getInstance()->getPdfs()->getAllPdfs($store->id);
        });
        $stores = $stores->all();

        return $this->renderTemplate('commerce/settings/pdfs/index',[
            'pdfs' => $pdfs,
            'stores' => $stores,
            'readOnly' => $this->isReadOnlyScreen(),
        ]);
    }

    /**
     * @param string|null $storeHandle
     * @param int|null $id
     * @param Pdf|null $pdf
     * @return Response
     * @throws Exception
     * @throws HttpException
     * @throws InvalidConfigException
     * @since 3.2
     */
    public function actionEdit(?string $storeHandle = null, int $id = null, Pdf $pdf = null): Response
    {
        if ($storeHandle === null || !$store = Plugin::getInstance()->getStores()->getStoreByHandle($storeHandle)) {
            $store = Plugin::getInstance()->getStores()->getPrimaryStore();
        }

        $variables = compact('pdf', 'id');

        $pdfLanguageOptions = [
            PdfRecord::LOCALE_ORDER_LANGUAGE => Craft::t('commerce', 'The language the order was made in.'),
        ];

        $variables['pdfLanguageOptions'] = array_merge($pdfLanguageOptions, LocaleHelper::getSiteAndOtherLanguages());

        if (!$variables['pdf']) {
            if ($variables['id']) {
                $variables['pdf'] = Plugin::getInstance()->getPdfs()->getPdfById($variables['id'], $store->id);

                if (!$variables['pdf']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['pdf'] = Craft::createObject([
                    'class' => Pdf::class,
                    'attributes' => ['storeId' => $store->id],
                ]);
            }
        }

        if ($variables['pdf']->id) {
            $variables['title'] = $variables['pdf']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new PDF');
        }

        $variables['isDefault'] = Plugin::getInstance()->getPdfs()->getAllPdfs($variables['pdf']->storeId)->count() === 0 || $variables['pdf']->isDefault;
        $variables['paperOrientationOptions'] = Pdf::getPaperOrientationOptions();
        $variables['paperSizeOptions'] = Pdf::getPaperSizeOptions();

        DebugPanel::prependOrAppendModelTab(model: $variables['pdf'], prepend: true);

        $variables['readOnly'] = $this->isReadOnlyScreen();

        return $this->renderTemplate('commerce/settings/pdfs/_edit', $variables);
    }

    /**
     * @throws BadRequestHttpException
     * @throws ErrorException
     * @throws Exception
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     * @since 3.2
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $pdfsService = Plugin::getInstance()->getPdfs();
        $pdfId = $this->request->getBodyParam('id');
        $storeId = $this->request->getBodyParam('storeId');

        if ($pdfId) {
            $pdf = $pdfsService->getPdfById($pdfId, $storeId);
            if (!$pdf) {
                throw new BadRequestHttpException("Invalid PDF ID: $pdfId");
            }
        } else {
            $pdf = new Pdf();
        }

        // Shared attributes
        $pdf->storeId = $storeId;
        $pdf->name = $this->request->getBodyParam('name');
        $pdf->handle = $this->request->getBodyParam('handle');
        $pdf->description = $this->request->getBodyParam('description');
        $pdf->templatePath = $this->request->getBodyParam('templatePath');
        $pdf->fileNameFormat = $this->request->getBodyParam('fileNameFormat');
        $pdf->enabled = $this->request->getBodyParam('enabled');
        $pdf->isDefault = $this->request->getBodyParam('isDefault');
        $pdf->language = $this->request->getBodyParam('language');
        $pdf->paperSize = $this->request->getBodyParam('paperSize');
        $pdf->paperOrientation = $this->request->getBodyParam('paperOrientation');

        // Save it
        if ($pdfsService->savePdf($pdf)) {
            $this->setSuccessFlash(Craft::t('commerce', 'PDF saved.'));
            return $this->redirectToPostedUrl($pdf);
        } else {
            $this->setFailFlash(Craft::t('commerce', 'Couldn’t save PDF.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['pdf' => $pdf]);

        return null;
    }

    /**
     * @throws HttpException
     * @since 3.2
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = $this->request->getRequiredBodyParam('id');

        Plugin::getInstance()->getPdfs()->deletePdfById($id);
        return $this->asSuccess();
    }

    /**
     * @throws \yii\db\Exception
     * @throws BadRequestHttpException
     * @since 3.2
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $ids = Json::decode($this->request->getRequiredBodyParam('ids'));

        if (!Plugin::getInstance()->getPdfs()->reorderPdfs($ids)) {
            return $this->asFailure(Craft::t('commerce', 'Couldn’t reorder PDFs.'));
        }

        return $this->asSuccess();
    }
}
