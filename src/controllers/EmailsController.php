<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\Email;
use craft\commerce\Plugin;
use craft\commerce\records\Email as EmailRecord;
use craft\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\Response;

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
     */
    public function actionIndex(): Response
    {
        $emails = Plugin::getInstance()->getEmails()->getAllEmails();
        return $this->renderTemplate('commerce/settings/emails/index', compact('emails'));
    }

    /**
     * @return Response
     */
    public function actionPreviewEmail(): Response
    {
        $emailId = Craft::$app->getRequest()->getParam('emailId');
        $email = Plugin::getInstance()->getEmails()->getEmailById($emailId);
        $orderNumber = Craft::$app->getRequest()->getParam('orderNumber');

        if ($orderNumber) {
            $order = Order::find()->shortNumber(substr($orderNumber, 0, 7))->one();
        } else {
            $order = Order::find()->isCompleted(true)->orderBy('RAND()')->one();
        }

        if ($email && $order && $template = $email->templatePath) {
            if ($email->recipientType == EmailRecord::TYPE_CUSTOMER) {
                // use the order's language for template rendering the email.
                $orderLanguage = $order->orderLanguage ?: Craft::$app->language;
                Craft::$app->language = $orderLanguage;
            }

            $view = Craft::$app->getView();
            $view->setTemplateMode($view::TEMPLATE_MODE_SITE);
            return $this->renderTemplate($template, compact('order'));
        }

        $errors = [];
        if (!$email) {
            $errors[] = Plugin::t('Could not find the email or template.');
        }

        if (!$order) {
            $errors[] = Plugin::t('Could not find the order.');
        }

        return $this->renderTemplate('commerce/settings/emails/_previewError', compact('errors'));
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
            $variables['title'] = Plugin::t('Create a new email');
        }

        $pdfs = Plugin::getInstance()->getPdfs()->getAllPdfs();
        $pdfList = [null => Plugin::t('Do not attach a PDF to this email')];
        $pdfList = ArrayHelper::merge($pdfList, ArrayHelper::map($pdfs, 'id', 'name'));
        $variables['pdfList'] = $pdfList;

        return $this->renderTemplate('commerce/settings/emails/_edit', $variables);
    }

    /**
     * @return null|Response
     * @throws BadRequestHttpException
     */
    public function actionSave()
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
        $email->pdfId = Craft::$app->getRequest()->getBodyParam('pdfId');

        // Save it
        if ($emailsService->saveEmail($email)) {
            Craft::$app->getSession()->setNotice(Plugin::t('Email saved.'));
            return $this->redirectToPostedUrl($email);
        } else {
            Craft::$app->getSession()->setError(Plugin::t('Couldn’t save email.'));
        }

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
