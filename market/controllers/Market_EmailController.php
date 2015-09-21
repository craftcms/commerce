<?php
namespace Craft;

/**
 * Class Market_EmailController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_EmailController extends Market_BaseController
{
    /**
     * @throws HttpException
     */
    public function actionIndex()
    {
        $this->requireAdmin();

        $emails = craft()->market_email->getAll();
        $this->renderTemplate('market/settings/emails/index',
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
        $this->requireAdmin();

        if (empty($variables['email'])) {
            if (!empty($variables['id'])) {
                $id                 = $variables['id'];
                $variables['email'] = craft()->market_email->getById($id);

                if (!$variables['email']->id) {
                    throw new HttpException(404);
                }
            } else {
                $variables['email'] = new Market_EmailModel();
            }
        }

        if (!empty($variables['id'])) {
            $variables['title'] = $variables['email']->name;
        } else {
            $variables['title'] = Craft::t('Create a new email');
        }

        $this->renderTemplate('market/settings/emails/_edit', $variables);
    }

    /**
     * @throws HttpException
     */
    public function actionSave()
    {
        $this->requireAdmin();

        $this->requirePostRequest();

        $email = new Market_EmailModel();

        // Shared attributes
        $email->id           = craft()->request->getPost('emailId');
        $email->name         = craft()->request->getPost('name');
        $email->subject      = craft()->request->getPost('subject');
        $email->to           = craft()->request->getPost('to');
        $email->bcc          = craft()->request->getPost('bcc');
        $email->enabled      = craft()->request->getPost('enabled');
        $email->templatePath = craft()->request->getPost('templatePath');

        // Save it
        if (craft()->market_email->save($email)) {
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
        $this->requireAdmin();

        $this->requirePostRequest();
        $this->requireAjaxRequest();

        $id = craft()->request->getRequiredPost('id');

        craft()->market_email->deleteById($id);
        $this->returnJson(['success' => true]);
    }

}