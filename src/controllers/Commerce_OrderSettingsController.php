<?php
namespace Craft;

/**
 * Class Commerce_OrderSettingsController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_OrderSettingsController extends Commerce_BaseController
{
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

		$variables['orderSettings'] = craft()->commerce_orderSettings->getByHandle('order');

		$variables['title'] = Craft::t('Order Settings');

		$this->renderTemplate('commerce/settings/ordersettings/_edit', $variables);
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

		$orderSettings = new Commerce_OrderSettingsModel();

		// Shared attributes
		$orderSettings->id = craft()->request->getPost('orderSettingsId');
		$orderSettings->name = 'Order';
		$orderSettings->handle = 'order';

		// Set the field layout
		$fieldLayout = craft()->fields->assembleLayoutFromPost();
		$fieldLayout->type = 'Commerce_Order';
		$orderSettings->setFieldLayout($fieldLayout);

		// Save it
		if (craft()->commerce_orderSettings->save($orderSettings))
		{
			craft()->userSession->setNotice(Craft::t('Order settings saved.'));
			$this->redirectToPostedUrl($orderSettings);
		}
		else
		{
			craft()->userSession->setError(Craft::t('Couldn’t save order settings.'));
		}

		craft()->urlManager->setRouteVariables(['orderSettings' => $orderSettings]);
	}

} 