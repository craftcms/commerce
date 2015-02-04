<?php
namespace Craft;

class Stripey_PaymentMethodController extends Stripey_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex()
	{
		$paymentMethods = craft()->stripey_paymentMethod->getAll();
		$this->renderTemplate('stripey/settings/paymentmethods/index', compact('paymentMethods'));
	}

	/**
	 * Create/Edit PaymentMethod
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit(array $variables = array())
	{
		if (empty($variables['paymentMethod']) && !empty($variables['class'])) {
			$class                      = $variables['class'];
			$variables['paymentMethod'] = craft()->stripey_paymentMethod->getByClass($class);
		}

		if (empty($variables['paymentMethod'])) {
			throw new HttpException(404);
		}

		$variables['title'] = $variables['paymentMethod']->name;
		$this->renderTemplate('stripey/settings/paymentmethods/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave()
	{
		$this->requirePostRequest();

		$paymentMethod = new Stripey_PaymentMethodModel();

		// Shared attributes
		$paymentMethod->class           = craft()->request->getPost('paymentMethodClass');
		$paymentMethod->settings        = craft()->request->getPost('settings');
		$paymentMethod->cpEnabled       = craft()->request->getPost('cpEnabled');
		$paymentMethod->frontendEnabled = craft()->request->getPost('frontendEnabled');

		// Save it
		if (craft()->stripey_paymentMethod->save($paymentMethod)) {
			craft()->userSession->setNotice(Craft::t('Payment Method saved.'));
			$this->redirectToPostedUrl($paymentMethod);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save payment method.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables(array(
			'paymentMethod' => $paymentMethod
		));
	}

	/**
	 * @throws HttpException
	 */
	public function actionDelete()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$id = craft()->request->getRequiredPost('id');

		craft()->stripey_paymentMethod->deleteById($id);
		$this->returnJson(array('success' => true));
	}

}