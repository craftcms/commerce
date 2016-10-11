<?php
namespace Craft;

/**
 * Class Commerce_EmailsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_EmailsController extends Commerce_BaseAdminController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $emails = craft()->commerce_emails->getAllEmails();
        $this->renderTemplate('commerce/settings/emails/index',
            compact('emails'));
    }

    /**
     * Create/Edit Email
     *
     * @param array $variables
     *
     * @throws HttpException
     */
    public function actionEdit(array $variables = [])
    {
        if (empty($variables['email'])) {
            if (!empty($variables['id'])) {
                $id = $variables['id'];
                $variables['email'] = craft()->commerce_emails->getEmailById($id);

                if (!$variables['email']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['email'] = new Commerce_EmailModel();
            }
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['email']->name;
        } else {
            $variables['title'] = Craft::t('Create a new email');
        }

        $this->renderTemplate('commerce/settings/emails/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requirePostRequest();

        $email = new Commerce_EmailModel();

        // Shared attributes
        $email->id = craft()->request->getPost('emailId');
        $email->name = craft()->request->getPost('name');
        $email->subject = craft()->request->getPost('subject');
        $email->recipientType = craft()->request->getPost('recipientType');
        $email->to = craft()->request->getPost('to');
        $email->bcc = craft()->request->getPost('bcc');
        $email->enabled = craft()->request->getPost('enabled');
        $email->templatePath = craft()->request->getPost('templatePath');

        // Save it
        if (craft()->commerce_emails->saveEmail($email)) {
            craft()->userSession->setNotice(Craft::t('Email saved.'));
            $this->redirectToPostedUrl($email);
        } else {
            craft()->userSession->setError(Craft::t('Couldn’t save email.'));
        }

        // Send the model back to the template
        craft()->urlManager->setRouteVariables(['email' => $email]);
    }

    /**
     * @throws HttpException
     */
    public function actionDelete()
    {
        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->commerce_emails->deleteEmailById($id);
        $this->returnJson(['success' => true]);
    }

}
