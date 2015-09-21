<?php
namespace Craft;

/**
 * Class Market_CountryController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_CountryController extends Market_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex ()
	{
		$this->requireAdmin();

		$countries = craft()->market_country->getAll();
		$this->renderTemplate('market/settings/countries/index',
			compact('countries'));
	}

	/**
	 * Create/Edit Country
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit (array $variables = [])
	{
		$this->requireAdmin();

		if (empty($variables['country']))
		{
			if (!empty($variables['id']))
			{
				$id = $variables['id'];
				$variables['country'] = craft()->market_country->getById($id);

				if (!$variables['country']->id)
				{
					throw new HttpException(404);
				}
			}
			else
			{
				$variables['country'] = new Market_CountryModel();
			}
		}

		if (!empty($variables['id']))
		{
			$variables['title'] = $variables['country']->name;
		}
		else
		{
			$variables['title'] = Craft::t('Create a new country');
		}

		$this->renderTemplate('market/settings/countries/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave ()
	{
		$this->requireAdmin();

		$this->requirePostRequest();

		$country = new Market_CountryModel();

		// Shared attributes
		$country->id = craft()->request->getPost('countryId');
		$country->name = craft()->request->getPost('name');
		$country->iso = craft()->request->getPost('iso');
		$country->stateRequired = craft()->request->getPost('stateRequired');

		// Save it
		if (craft()->market_country->save($country))
		{
			craft()->userSession->setNotice(Craft::t('Country saved.'));
			$this->redirectToPostedUrl($country);
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save country.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables(['country' => $country]);
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

		try
		{
			craft()->market_country->deleteById($id);
			$this->returnJson(['success' => true]);
		}
		catch (\Exception $e)
		{
			$this->returnErrorJson($e->getMessage());
		}
	}

}