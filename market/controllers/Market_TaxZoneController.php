<?php
namespace Craft;

/**
 * Class Market_TaxZoneController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_TaxZoneController extends Market_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex ()
	{
		$this->requireAdmin();

		$taxZones = craft()->market_taxZone->getAll();
		$this->renderTemplate('market/settings/taxzones/index',
			compact('taxZones'));
	}

	/**
	 * Create/Edit TaxZone
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit (array $variables = [])
	{
		$this->requireAdmin();

		if (empty($variables['taxZone']))
		{
			if (!empty($variables['id']))
			{
				$id = $variables['id'];
				$variables['taxZone'] = craft()->market_taxZone->getById($id);

				if (!$variables['taxZone']->id)
				{
					throw new HttpException(404);
				}
			}
			else
			{
				$variables['taxZone'] = new Market_TaxZoneModel();
			};
		}

		if (!empty($variables['id']))
		{
			$variables['title'] = $variables['taxZone']->name;
		}
		else
		{
			$variables['title'] = Craft::t('Create a Tax Zone');
		}

		$countries = craft()->market_country->getAll();
		$states = craft()->market_state->getAll();

		$variables['countries'] = \CHtml::listData($countries, 'id', 'name');
		$variables['states'] = \CHtml::listData($states, 'id', 'name');

		$this->renderTemplate('market/settings/taxzones/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave ()
	{
		$this->requireAdmin();
		$this->requirePostRequest();

		$taxZone = new Market_TaxZoneModel();

		// Shared attributes
		$taxZone->id = craft()->request->getPost('taxZoneId');
		$taxZone->name = craft()->request->getPost('name');
		$taxZone->description = craft()->request->getPost('description');
		$taxZone->countryBased = craft()->request->getPost('countryBased');
		$taxZone->default = craft()->request->getPost('default');
		$countriesIds = craft()->request->getPost('countries', []);
		$statesIds = craft()->request->getPost('states', []);

		// Save it
		if (craft()->market_taxZone->save($taxZone, $countriesIds,
			$statesIds)
		)
		{
			craft()->userSession->setNotice(Craft::t('Tax Zone saved.'));
			$this->redirectToPostedUrl($taxZone);
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save tax zone.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables(['taxZone' => $taxZone]);
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

		craft()->market_taxZone->deleteById($id);
		$this->returnJson(['success' => true]);
	}

}