<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Email;
use craft\commerce\Plugin;
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
    // Public Methods
    // =========================================================================

    /**
     * @return Response
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

        return $this->renderTemplate('commerce/settings/emails/_edit', $variables);
    }

    /**
     * @return null|Response
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $email = new Email();

        // Shared attributes
        $email->id = Craft::$app->getRequest()->getBodyParam('emailId');
        $email->name = Craft::$app->getRequest()->getBodyParam('name');
        $email->subject = Craft::$app->getRequest()->getBodyParam('subject');
        $email->recipientType = Craft::$app->getRequest()->getBodyParam('recipientType');
        $email->to = Craft::$app->getRequest()->getBodyParam('to');
        $email->bcc = Craft::$app->getRequest()->getBodyParam('bcc');
        $email->enabled = (bool)Craft::$app->getRequest()->getBodyParam('enabled');
        $email->templatePath = Craft::$app->getRequest()->getBodyParam('templatePath');
        $email->attachPdf = Craft::$app->getRequest()->getBodyParam('attachPdf');
        // Only set pdfTemplatePath if attachments are turned on
        $email->pdfTemplatePath = $email->attachPdf ? Craft::$app->getRequest()->getBodyParam('pdfTemplatePath') : '';

        // Save it
        if (Plugin::getInstance()->getEmails()->saveEmail($email)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Email saved.'));
            return $this->redirectToPostedUrl($email);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save email.'));
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
