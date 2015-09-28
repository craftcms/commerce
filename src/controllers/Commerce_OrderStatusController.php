<?php
namespace Craft;

/**
 * Class Commerce_OrderStatusController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_OrderStatusController extends Commerce_BaseController
{
	public function actionIndex (array $variables = [])
	{
		if(!craft()->userSession->getUser()->can('accessCommerce')){
			throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
		}

		$variables['orderStatuses'] = craft()->commerce_orderStatus->getAll();

		$this->renderTemplate('commerce/settings/orderstatuses/index', $variables);
	}


	/**
	 * @param array $variables
	 *
	 * @throws HttpException
	 */
	public function actionEdit (array $variables = [])
	{

		if(!craft()->userSession->getUser()->can('accessCommerce')){
			throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
		}

		if (empty($variables['orderStatus']))
		{
			if (!empty($variables['id']))
			{
				$variables['orderStatus'] = craft()->commerce_orderStatus->getById($variables['id']);
				$variables['orderStatusId'] = $variables['orderStatus']->id;
				if (!$variables['orderStatus']->id)
				{
					throw new HttpException(404);
				}
			}
			else
			{
				$variables['orderStatus'] = new Commerce_OrderStatusModel();
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

		$emails = craft()->commerce_email->getAll(['order' => 'name']);
		$variables['emails'] = \CHtml::listData($emails, 'id', 'name');

		$this->renderTemplate('commerce/settings/orderstatuses/_edit',
			$variables);
	}

	/**
	 * @throws Exception
	 * @throws HttpException
	 * @throws \Exception
	 */
	public function actionSave ()
	{
		if(!craft()->userSession->getUser()->can('accessCommerce')){
			throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
		}

		$this->requirePostRequest();

		$orderStatus = new Commerce_OrderStatusModel();

		// Shared attributes
		$orderStatus->id = craft()->request->getPost('orderStatusId');
		$orderStatus->name = craft()->request->getPost('name');
		$orderStatus->handle = craft()->request->getPost('handle');
		$orderStatus->color = craft()->request->getPost('color');
		$orderStatus->default = craft()->request->getPost('default');
		$emailsIds = craft()->request->getPost('emails', []);

		// Save it
		if (craft()->commerce_orderStatus->save($orderStatus, $emailsIds))
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
		if(!craft()->userSession->getUser()->can('accessCommerce')){
			throw new HttpException(403, Craft::t('This action is not allowed for the current user.'));
		}

		$this->requireAjaxRequest();

		$orderStatusId = craft()->request->getRequiredPost('id');

		if (craft()->commerce_orderStatus->deleteById($orderStatusId))
		{
			$this->returnJson(['success' => true]);
		};
	}

} 