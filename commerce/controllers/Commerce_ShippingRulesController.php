<?php
namespace Craft;

/**
 * Class Commerce_ShippingRulesController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_ShippingRulesController extends Commerce_BaseAdminController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex ()
	{
		if (!craft()->userSession->getUser()->can('manageCommerce'))
		{
			throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
		}

		$methodsExist = craft()->commerce_shippingMethods->exists();
		$shippingRules = craft()->commerce_shippingRules->getAll([
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
		if (!craft()->userSession->getUser()->can('manageCommerce'))
		{
			throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
		}

		if (empty($variables['shippingRule']))
		{
			if (!empty($variables['ruleId']))
			{
				$id = $variables['ruleId'];
				$variables['shippingRule'] = craft()->commerce_shippingRules->getById($id);

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

		$variables['countries'] = ['' => ''] + craft()->commerce_countries->getFormList();
		$variables['states'] = craft()->commerce_states->getGroupedByCountries();

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
		if (!craft()->userSession->getUser()->can('manageCommerce'))
		{
			throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
		}

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
		if (craft()->commerce_shippingRules->save($shippingRule))
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
		if (!craft()->userSession->getUser()->can('manageCommerce'))
		{
			throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
		}

		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$ids = JsonHelper::decode(craft()->request->getRequiredPost('ids'));
		$success = craft()->commerce_shippingRules->reorder($ids);

		return $this->returnJson(['success' => $success]);
	}

	/**
	 * @throws HttpException
	 */
	public function actionDelete ()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$id = craft()->request->getRequiredPost('id');

		craft()->commerce_shippingRules->deleteById($id);
		$this->returnJson(['success' => true]);
	}

}