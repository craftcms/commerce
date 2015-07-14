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
class Market_ShippingMethodController extends Market_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex()
	{
		$shippingMethods = craft()->market_shippingMethod->getAll();
		$this->renderTemplate('market/settings/shippingmethods/index', compact('shippingMethods'));
	}

	/**
	 * Create/Edit Shipping Method
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit(array $variables = [])
	{
		if (empty($variables['shippingMethod'])) {
			if (!empty($variables['id'])) {
				$id                          = $variables['id'];
				$variables['shippingMethod'] = craft()->market_shippingMethod->getById($id);
				$variables['newMethod']      = false;

				if (!$variables['shippingMethod']->id) {
					throw new HttpException(404);
				}
			} else {
				$variables['shippingMethod'] = new Market_ShippingMethodModel();
				$variables['newMethod']      = true;
			}
		}

		if (!empty($variables['id'])) {
			$variables['title'] = $variables['shippingMethod']->name;
		} else {
			$variables['title'] = Craft::t('Create a Shipping Method');
		}

		$shippingRules = craft()->market_shippingRule->getAllByMethodId($variables['shippingMethod']->id);

		$variables['shippingRules'] = $shippingRules;

		$this->renderTemplate('market/settings/shippingmethods/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave()
	{
		$this->requirePostRequest();

		$shippingMethod = new Market_ShippingMethodModel();

		// Shared attributes
		$shippingMethod->id      = craft()->request->getPost('shippingMethodId');
		$shippingMethod->name    = craft()->request->getPost('name');
		$shippingMethod->enabled = craft()->request->getPost('enabled');
		$shippingMethod->default = craft()->request->getPost('default');

		// Save it
		if (craft()->market_shippingMethod->save($shippingMethod)) {
			craft()->userSession->setNotice(Craft::t('Shipping method saved.'));
			$this->redirectToPostedUrl($shippingMethod);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save shipping method.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables(['shippingMethod' => $shippingMethod]);
	}

	/**
	 * @throws HttpException
	 */
	public function actionDelete()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$id = craft()->request->getRequiredPost('id');

		$method = craft()->market_shippingMethod->getById($id);

		if ($method->default){
			$this->returnJson(array(
				'errors' => [Craft::t('Can not delete the default method.')]
			));
		}

		if (craft()->market_shippingMethod->delete($method)) {
			$this->returnJson(['success' => true]);
		}

	}

}