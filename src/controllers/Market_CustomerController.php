<?php
namespace Craft;

/**
 * Class Market_CustomerController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_CustomerController extends Market_BaseController
{
	/**
	 * @throws HttpException
	 */
	public function actionIndex ()
	{
		$this->requireAdmin();

		$customers = craft()->market_customer->getAll(['with' => 'user']);
		$this->renderTemplate('market/customers/index', compact('customers'));
	}

	/**
	 * Edit Customer
	 *
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit (array $variables = [])
	{
		$this->requireAdmin();

		if (empty($variables['customer']))
		{
			if (empty($variables['id']))
			{
				throw new HttpException(404);
			}

			$id = $variables['id'];
			$variables['customer'] = craft()->market_customer->getById($id);

			if (!$variables['customer']->id)
			{
				throw new HttpException(404);
			}
		}

		$variables['title'] = Craft::t('Customer #{id}',
			['id' => $variables['id']]);

		$this->renderTemplate('market/customers/_edit', $variables);
	}

	/**
	 * @throws HttpException
	 */
	public function actionSave ()
	{
		$this->requireAdmin();

		$this->requirePostRequest();

		$id = craft()->request->getRequiredPost('id');
		$customer = craft()->market_customer->getById($id);

		if (!$customer->id)
		{
			throw new HttpException(400);
		}

		// Shared attributes
		$customer->email = craft()->request->getPost('email');

		// Save it
		if (craft()->market_customer->save($customer))
		{
			craft()->userSession->setNotice(Craft::t('Customer saved.'));
			$this->redirectToPostedUrl();
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save customer.'));
		}

		// Send the model back to the template
		craft()->urlManager->setRouteVariables(['customer' => $customer]);
	}

}