<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Pdf;
use craft\commerce\Plugin;
use craft\commerce\records\Pdf as PdfRecord;
use craft\helpers\Json;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Pdfs Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.2
 */
class PdfsController extends BaseAdminController
{
    /**
     * @return Response
     * @since 3.2
     */
    public function actionIndex(): Response
    {
        $pdfs = Plugin::getInstance()->getPdfs()->getAllPdfs();
        return $this->renderTemplate('commerce/settings/pdfs/index', compact('pdfs'));
    }

    /**
     * @param int|null $id
     * @param Pdf|null $pdf
     * @return Response
     * @throws HttpException
     * @since 3.2
     */
    public function actionEdit(int $id = null, Pdf $pdf = null): Response
    {
        $variables = compact('pdf', 'id');
        
        $pdfLanguageOptions = [
          PdfRecord::TYPE_LOCALE_CREATED => Craft::t('commerce', 'The language the order was made in.')
        ];
        
        // get current site's locale
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $locale = Craft::$app->getI18n()->getLocaleById($site->language);
  
            $pdfLanguageOptions[$site->id] = Craft::t('commerce', $site->name . ' - ' . $locale->getDisplayName());
        }
        
        $variables['pdfLanguageOptions'] = $pdfLanguageOptions;
        
        if (!$variables['pdf']) {
            if ($variables['id']) {
                $variables['pdf'] = Plugin::getInstance()->getPdfs()->getPdfById($variables['id']);

                if (!$variables['pdf']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['pdf'] = new Pdf();
            }
        }

        if ($variables['pdf']->id) {
            $variables['title'] = $variables['pdf']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new PDF');
        }

        return $this->renderTemplate('commerce/settings/pdfs/_edit', $variables);
    }

    /**
     * @return null|Response
     * @throws HttpException
     * @since 3.2
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $pdfsService = Plugin::getInstance()->getPdfs();
        $pdfId = $this->request->getBodyParam('id');

        if ($pdfId) {
            $pdf = $pdfsService->getPdfById($pdfId);
            if (!$pdf) {
                throw new BadRequestHttpException("Invalid PDF ID: $pdfId");
            }
        } else {
            $pdf = new Pdf();
        }

        // Shared attributes
        $pdf->name = Craft::$app->getRequest()->getBodyParam('name');
        $pdf->handle = Craft::$app->getRequest()->getBodyParam('handle');
        $pdf->description = Craft::$app->getRequest()->getBodyParam('description');
        $pdf->templatePath = Craft::$app->getRequest()->getBodyParam('templatePath');
        $pdf->fileNameFormat = Craft::$app->getRequest()->getBodyParam('fileNameFormat');
        $pdf->enabled = Craft::$app->getRequest()->getBodyParam('enabled');
        $pdf->isDefault = Craft::$app->getRequest()->getBodyParam('isDefault');
        $pdf->locale = Craft::$app->getRequest()->getBodyParam('locale');

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

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getPdfs()->deletePdfById($id);
        return $this->asJson(['success' => true]);
    }

    /**
     * @return Response
     * @throws \yii\db\Exception
     * @throws BadRequestHttpException
     * @since 3.2
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $ids = Json::decode(Craft::$app->getRequest()->getRequiredBodyParam('ids'));

        if ($success = Plugin::getInstance()->getPdfs()->reorderPdfs($ids)) {
            return $this->asJson(['success' => $success]);
        }

        return $this->asJson(['error' => Craft::t('commerce', 'Couldn’t reorder PDFs.')]);
    }
}
