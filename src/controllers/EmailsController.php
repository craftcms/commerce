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
use craft\commerce\models\Email;
use craft\commerce\Plugin;
use craft\commerce\records\Email as EmailRecord;
use craft\helpers\ArrayHelper;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class Emails Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class EmailsController extends BaseAdminController
{
    /**
     * @return Response
     * @throws InvalidConfigException
     */
    public function actionIndex(): Response
    {
        $emails = Plugin::getInstance()->getEmails()->getAllEmails();
        return $this->renderTemplate('commerce/settings/emails/index', compact('emails'));
    }

    /**
     * @param int|null $id
     * @param Email|null $email
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Email $email = null): Response
    {
        $variables = compact('email', 'id');

        if (!$variables['email']) {
            if ($variables['id']) {
                $variables['email'] = Plugin::getInstance()->getEmails()->getEmailById($variables['id']);

                if (!$variables['email']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['email'] = new Email();
            }
        }

        if ($variables['email']->id) {
            $variables['title'] = $variables['email']->name;
        } else {
            $variables['title'] = Craft::t('commerce', 'Create a new email');
        }

        DebugPanel::prependModelTab($variables['email']);

        $pdfs = Plugin::getInstance()->getPdfs()->getAllPdfs();
        $pdfList = [null => Craft::t('commerce', 'Do not attach a PDF to this email')];
        $pdfList = ArrayHelper::merge($pdfList, ArrayHelper::map($pdfs, 'id', 'name'));
        $variables['pdfList'] = $pdfList;

        $emailLanguageOptions = [
            EmailRecord::LOCALE_ORDER_LANGUAGE => Craft::t('commerce', 'The language the order was made in.'),
        ];

        $variables['emailLanguageOptions'] = array_merge($emailLanguageOptions, LocaleHelper::getSiteAndOtherLanguages());

        return $this->renderTemplate('commerce/settings/emails/_edit', $variables);
    }

    /**
     * @return null|Response
     * @throws BadRequestHttpException
     * @throws ErrorException
     * @throws Exception
     * @throws NotSupportedException
     * @throws ServerErrorHttpException
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $emailsService = Plugin::getInstance()->getEmails();
        $emailId = $this->request->getBodyParam('emailId');

        if ($emailId) {
            $email = $emailsService->getEmailById($emailId);
            if (!$email) {
                throw new BadRequestHttpException("Invalid email ID: $emailId");
            }
        } else {
            $email = new Email();
        }

        // Shared attributes
        $email->name = Craft::$app->getRequest()->getBodyParam('name');
        $email->subject = Craft::$app->getRequest()->getBodyParam('subject');
        $email->recipientType = Craft::$app->getRequest()->getBodyParam('recipientType');
        $email->to = Craft::$app->getRequest()->getBodyParam('to');
        $email->bcc = Craft::$app->getRequest()->getBodyParam('bcc');
        $email->cc = Craft::$app->getRequest()->getBodyParam('cc');
        $email->replyTo = Craft::$app->getRequest()->getBodyParam('replyTo');
        $email->enabled = (bool)Craft::$app->getRequest()->getBodyParam('enabled');
        $email->templatePath = Craft::$app->getRequest()->getBodyParam('templatePath');
        $email->plainTextTemplatePath = Craft::$app->getRequest()->getBodyParam('plainTextTemplatePath');
        $pdfId = Craft::$app->getRequest()->getBodyParam('pdfId');
        $email->pdfId = $pdfId ?: null;
        $email->language = Craft::$app->getRequest()->getBodyParam('language');

        // Save it
        if ($emailsService->saveEmail($email)) {
            $this->setSuccessFlash(Craft::t('commerce', 'Email saved.'));
            return $this->redirectToPostedUrl($email);
        }

        $this->setFailFlash(Craft::t('commerce', 'Couldn’t save email.'));
        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['email' => $email]);

        return null;
    }

    /**
     * @throws HttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredBodyParam('id');

        Plugin::getInstance()->getEmails()->deleteEmailById($id);
        return $this->asJson(['success' => true]);
    }
}
