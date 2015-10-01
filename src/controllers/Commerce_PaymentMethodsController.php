<?php
namespace Craft;

/**
 * Class Commerce_PaymentMethodsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_PaymentMethodsController extends Commerce_BaseAdminController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex ()
	{
		$paymentMethods = craft()->commerce_paymentMethods->getAllPossibleGateways();
		$this->renderTemplate('commerce/settings/paymentmethods/index', compact('paymentMethods'));
	}

	/**
	 * Create/Edit PaymentMethod
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit (array $variables = [])
	{
		if (empty($variables['paymentMethod']) && !empty($variables['class']))
		{
			$class = $variables['class'];
			$variables['paymentMethod'] = craft()->commerce_paymentMethods->getByClass($class);
		}

		if (empty($variables['paymentMethod']))
		{
			throw new HttpException(404);
		}

		$variables['title'] = $variables['paymentMethod']->name;
		$this->renderTemplate('commerce/settings/paymentmethods/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave ()
	{
		$this->requirePostRequest();

		$paymentMethod = new Commerce_PaymentMethodModel();

		// Shared attributes
		$paymentMethod->class = craft()->request->getPost('paymentMethodClass');
		$paymentMethod->settings = craft()->request->getPost('settings', []);
		$paymentMethod->cpEnabled = craft()->request->getPost('cpEnabled');
		$paymentMethod->frontendEnabled = craft()->request->getPost('frontendEnabled');

		// Save it
		if (craft()->commerce_paymentMethods->save($paymentMethod))
		{
			craft()->userSession->setNotice(Craft::t('Payment Method saved.'));
			$this->redirectToPostedUrl($paymentMethod);
		}
		else
		{
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
	public function actionDelete ()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$id = craft()->request->getRequiredPost('id');

		craft()->commerce_paymentMethods->deleteById($id);
		$this->returnJson(['success' => true]);
	}

}