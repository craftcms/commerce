<?php
namespace Craft;

/**
 * Class Commerce_ShippingRuleController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ShippingRuleController extends Commerce_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex ()
	{
		$this->requireAdmin();

		$methodsExist = craft()->commerce_shippingMethod->exists();
		$shippingRules = craft()->commerce_shippingRule->getAll([
			'order' => 't.methodId, t.name',
			'with'  => ['method', 'country', 'state'],
		]);
		$this->renderTemplate('commerce/settings/shippingrules/index', compact('shippingRules', 'methodsExist'));
	}

	/**
	 * Create/Edit Shipping Rule
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit (array $variables = [])
	{
		$this->requireAdmin();

		if (empty($variables['shippingRule']))
		{
			if (!empty($variables['ruleId']))
			{
				$id = $variables['ruleId'];
				$variables['shippingRule'] = craft()->commerce_shippingRule->getById($id);

				if (!$variables['shippingRule']->id)
				{
					throw new HttpException(404);
				}
			}
			else
			{
				$variables['shippingRule'] = new Commerce_ShippingRuleModel();
			}
		}

		$variables['countries'] = ['' => ''] + craft()->commerce_country->getFormList();
		$variables['states'] = craft()->commerce_state->getGroupedByCountries();

		if (!empty($variables['ruleId']))
		{
			$variables['title'] = $variables['shippingRule']->name;
		}
		else
		{
			$variables['title'] = Craft::t('Create a new shipping rule');
		}

		$this->renderTemplate('commerce/settings/shippingrules/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave ()
	{
		$this->requireAdmin();
		$this->requirePostRequest();

		$shippingRule = new Commerce_ShippingRuleModel();

		// Shared attributes
		$fields = ['id', 'name', 'description', 'countryId', 'stateId', 'methodId', 'enabled', 'minQty', 'maxQty', 'minTotal', 'maxTotal',
			'minWeight', 'maxWeight', 'baseRate', 'perItemRate', 'weightRate', 'percentageRate', 'minRate', 'maxRate'];
		foreach ($fields as $field)
		{
			$shippingRule->$field = craft()->request->getPost($field);
		}

		// Save it
		if (craft()->commerce_shippingRule->save($shippingRule))
		{
			craft()->userSession->setNotice(Craft::t('Shipping rule saved.'));
			$this->redirectToPostedUrl($shippingRule);
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save shipping rule.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables(['shippingRule' => $shippingRule]);
	}

	/**
	 * @return null
	 * @throws HttpException
	 */
	public function actionReorder ()
	{
		$this->requireAdmin();
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$ids = JsonHelper::decode(craft()->request->getRequiredPost('ids'));
		$success = craft()->commerce_shippingRule->reorder($ids);

		return $this->returnJson(['success' => $success]);
	}

	/**
	 * @throws HttpException
	 */
	public function actionDelete ()
	{
		$this->requireAdmin();
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$id = craft()->request->getRequiredPost('id');

		craft()->commerce_shippingRule->deleteById($id);
		$this->returnJson(['success' => true]);
	}

}