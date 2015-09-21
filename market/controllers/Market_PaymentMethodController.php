<?php
namespace Craft;

/**
 * Class Market_PaymentMethodController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_PaymentMethodController extends Market_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex()
	{
		$this->requireAdmin();

		$paymentMethods = craft()->market_paymentMethod->getAllPossibleGateways();
		$this->renderTemplate('market/settings/paymentmethods/index', compact('paymentMethods'));
	}

	/**
	 * Create/Edit PaymentMethod
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit(array $variables = [])
	{
		$this->requireAdmin();

		if (empty($variables['paymentMethod']) && !empty($variables['class'])) {
			$class                      = $variables['class'];
			$variables['paymentMethod'] = craft()->market_paymentMethod->getByClass($class);
		}

		if (empty($variables['paymentMethod'])) {
			throw new HttpException(404);
		}

		$variables['title'] = $variables['paymentMethod']->name;
		$this->renderTemplate('market/settings/paymentmethods/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave()
	{
		$this->requireAdmin();
		$this->requirePostRequest();

		$paymentMethod = new Market_PaymentMethodModel();

		// Shared attributes
		$paymentMethod->class           = craft()->request->getPost('paymentMethodClass');
		$paymentMethod->settings        = craft()->request->getPost('settings',[]);
		$paymentMethod->cpEnabled       = craft()->request->getPost('cpEnabled');
		$paymentMethod->frontendEnabled = craft()->request->getPost('frontendEnabled');

		// Save it
		if (craft()->market_paymentMethod->save($paymentMethod)) {
			craft()->userSession->setNotice(Craft::t('Payment Method saved.'));
			$this->redirectToPostedUrl($paymentMethod);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save payment method.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables([
			'paymentMethod' => $paymentMethod
		]);
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

		craft()->market_paymentMethod->deleteById($id);
		$this->returnJson(['success' => true]);
	}

}