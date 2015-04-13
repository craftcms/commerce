<?php
namespace Craft;

/**
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_EmailController extends Market_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex()
	{
		$emails = craft()->market_email->getAll();
		$this->renderTemplate('market/settings/emails/index', compact('emails'));
	}

	/**
	 * Create/Edit Email
	 *
	 * @param array $variables
	 * @throws HttpException
	 */
	public function actionEdit(array $variables = [])
	{
		if (empty($variables['email'])) {
			if (!empty($variables['id'])) {
				$id = $variables['id'];
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
			$variables['title'] = Craft::t('Create a Email');
		}

		$this->renderTemplate('market/settings/emails/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave()
	{
		$this->requirePostRequest();

		$email = new Market_EmailModel();

		// Shared attributes
        $email->id           = craft()->request->getPost('emailId');
        $email->name         = craft()->request->getPost('name');
        $email->subject      = craft()->request->getPost('subject');
        $email->to           = craft()->request->getPost('to');
        $email->bcc          = craft()->request->getPost('bcc');
        $email->type         = craft()->request->getPost('type');
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
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$id = craft()->request->getRequiredPost('id');

		craft()->market_email->deleteById($id);
		$this->returnJson(['success' => true]);
	}

}