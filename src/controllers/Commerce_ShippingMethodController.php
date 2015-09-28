<?php
namespace Craft;

/**
 * Class Commerce_ShippingMethodController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ShippingMethodController extends Commerce_BaseAdminController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex ()
	{
		$shippingMethods = craft()->commerce_shippingMethod->getAll();
		$this->renderTemplate('commerce/settings/shippingmethods/index', compact('shippingMethods'));
	}

	/**
	 * Create/Edit Shipping Method
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit (array $variables = [])
	{
		$variables['newMethod'] = false;

		if (empty($variables['shippingMethod']))
		{
			if (!empty($variables['id']))
			{
				$id = $variables['id'];
				$variables['shippingMethod'] = craft()->commerce_shippingMethod->getById($id);

				if (!$variables['shippingMethod']->id)
				{
					throw new HttpException(404);
				}
			}
			else
			{
				$variables['shippingMethod'] = new Commerce_ShippingMethodModel();
				$variables['newMethod'] = true;
			}
		}

		if (!empty($variables['id']))
		{
			$variables['title'] = $variables['shippingMethod']->name;
		}
		else
		{
			$variables['title'] = Craft::t('Create a new shipping method');
			$variables['newMethod'] = true;
		}

		$shippingRules = craft()->commerce_shippingRule->getAllByMethodId($variables['shippingMethod']->id);

		$variables['shippingRules'] = $shippingRules;

		$this->renderTemplate('commerce/settings/shippingmethods/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave ()
	{
		$this->requirePostRequest();
		$shippingMethod = new Commerce_ShippingMethodModel();

		// Shared attributes
		$shippingMethod->id = craft()->request->getPost('shippingMethodId');
		$shippingMethod->name = craft()->request->getPost('name');
		$shippingMethod->enabled = craft()->request->getPost('enabled');
		$shippingMethod->default = craft()->request->getPost('default');
		// Save it
		if (craft()->commerce_shippingMethod->save($shippingMethod))
		{
			craft()->userSession->setNotice(Craft::t('Shipping method saved.'));
			$this->redirectToPostedUrl($shippingMethod);
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save shipping method.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables(['shippingMethod' => $shippingMethod]);
	}

	/**
	 * @throws HttpException
	 */
	public function actionDelete ()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$id = craft()->request->getRequiredPost('id');

		$method = craft()->commerce_shippingMethod->getById($id);

		if ($method->default)
		{
			$this->returnJson([
				'errors' => [Craft::t('Can not delete the default method.')]
			]);
		}

		if (craft()->commerce_shippingMethod->delete($method))
		{
			$this->returnJson(['success' => true]);
		}
	}

}