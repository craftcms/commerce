<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Email;
use craft\commerce\Plugin;
use yii\web\HttpException;
use yii\web\Response;

/**
 * Class Emails Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
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
     * @param int|null   $id
     * @param Email|null $email
     *
     * @return Response
     * @throws HttpException
     */
    public function actionEdit(int $id = null, Email $email = null): Response
    {
        $variables = [
            'email' => $email,
            'id' => $id
        ];

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
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $email = new Email();

        // Shared attributes
        $email->id = Craft::$app->getRequest()->getParam('emailId');
        $email->name = Craft::$app->getRequest()->getParam('name');
        $email->subject = Craft::$app->getRequest()->getParam('subject');
        $email->recipientType = Craft::$app->getRequest()->getParam('recipientType');
        $email->to = Craft::$app->getRequest()->getParam('to');
        $email->bcc = Craft::$app->getRequest()->getParam('bcc');
        $email->enabled = Craft::$app->getRequest()->getParam('enabled');
        $email->templatePath = Craft::$app->getRequest()->getParam('templatePath');

        // Save it
        if (Plugin::getInstance()->getEmails()->saveEmail($email)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Email saved.'));
            $this->redirectToPostedUrl($email);
        } else {
            Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t save email.'));
        }

        // Send the model back to the template
        Craft::$app->getUrlManager()->setRouteParams(['email' => $email]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = Craft::$app->getRequest()->getRequiredParam('id');

        Plugin::getInstance()->getEmails()->deleteEmailById($id);
        return $this->asJson(['success' => true]);
    }
}
