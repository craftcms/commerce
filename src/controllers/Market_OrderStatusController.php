<?php
namespace Craft;

/**
 * Class Market_OrderStatusController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_OrderStatusController extends Market_BaseController
{
	public function actionIndex (array $variables = [])
	{
		$this->requireAdmin();

		$variables['orderStatuses'] = craft()->market_orderStatus->getAll();

		$this->renderTemplate('market/settings/orderstatuses/index', $variables);
	}


	/**
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit (array $variables = [])
	{

		$this->requireAdmin();

		if (empty($variables['orderStatus']))
		{
			if (!empty($variables['id']))
			{
				$variables['orderStatus'] = craft()->market_orderStatus->getById($variables['id']);
				$variables['orderStatusId'] = $variables['orderStatus']->id;
				if (!$variables['orderStatus']->id)
				{
					throw new HttpException(404);
				}
			}
			else
			{
				$variables['orderStatus'] = new Market_OrderStatusModel();
			}
		}

		if (!empty($variables['orderStatusId']))
		{
			$variables['title'] = $variables['orderStatus']->name;
		}
		else
		{
			$variables['title'] = Craft::t('Create a new custom status');
		}

		$emails = craft()->market_email->getAll(['order' => 'name']);
		$variables['emails'] = \CHtml::listData($emails, 'id', 'name');

		$this->renderTemplate('market/settings/orderstatuses/_edit',
			$variables);
	}

	/**
	 * @throws Exception
	 * @throws HttpException
	 * @throws \Exception
	 */
	public function actionSave ()
	{
		$this->requireAdmin();
		$this->requirePostRequest();

		$orderStatus = new Market_OrderStatusModel();

		// Shared attributes
		$orderStatus->id = craft()->request->getPost('orderStatusId');
		$orderStatus->name = craft()->request->getPost('name');
		$orderStatus->handle = craft()->request->getPost('handle');
		$orderStatus->color = craft()->request->getPost('color');
		$orderStatus->default = craft()->request->getPost('default');
		$emailsIds = craft()->request->getPost('emails', []);

		// Save it
		if (craft()->market_orderStatus->save($orderStatus, $emailsIds))
		{
			craft()->userSession->setNotice(Craft::t('Order status saved.'));
			$this->redirectToPostedUrl($orderStatus);
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save order status.'));
		}

		craft()->urlManager->setRouteVariables(compact('orderStatus', 'emailsIds'));
	}

	/**
	 * @throws HttpException
	 */
	public function actionDelete ()
	{
		$this->requireAdmin();
		$this->requireAjaxRequest();

		$orderStatusId = craft()->request->getRequiredPost('id');

		if (craft()->market_orderStatus->deleteById($orderStatusId))
		{
			$this->returnJson(['success' => true]);
		};
	}

} 