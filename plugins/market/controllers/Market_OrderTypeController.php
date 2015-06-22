<?php
namespace Craft;

/**
 *
 *
 * @author    Make with Morph. <support@makewithmorph.com>
 * @copyright Copyright (c) 2015, Luke Holder.
 * @license   http://makewithmorph.com/market/license Market License Agreement
 * @see       http://makewithmorph.com
 * @package   craft.plugins.market.controllers
 * @since     0.1
 */
class Market_OrderTypeController extends Market_BaseController
{
    /**
     * @throws HttpException
     */
	public function actionIndex()
	{
		$methodsExist = craft()->market_shippingMethod->exists();
		$orderTypes   = craft()->market_orderType->getAll(['with' => 'shippingMethod', 'order' => 't.name']);
		$this->renderTemplate('market/settings/ordertypes/index', compact('orderTypes', 'methodsExist'));
	}

    /**
     * @param array $variables
     * @throws HttpException
     */
	public function actionEditOrderType(array $variables = [])
	{

		if (empty($variables['orderType'])) {
			if (!empty($variables['orderTypeId'])) {
				$orderTypeId            = $variables['orderTypeId'];
				$variables['orderType'] = craft()->market_orderType->getById($orderTypeId);

				if (!$variables['orderType']->id) {
					throw new HttpException(404);
				}
			} else {
				$variables['orderType'] = new Market_OrderTypeModel();
			}
		}

		if (!empty($variables['orderTypeId'])) {
			$variables['title'] = $variables['orderType']->name. " ". Craft::t('Order Type');;
		} else {
			$variables['title'] = Craft::t('Create a Order Type');
		}

		$shippingMethods              = craft()->market_shippingMethod->getAll(['order' => 'name']);
		$variables['shippingMethods'] = \CHtml::listData($shippingMethods, 'id', 'name');

		$cartsToPurge                 = craft()->market_orderType->getCartsToPurge($variables['orderType']);
		$variables['cartsToPurge']    = count($cartsToPurge);

		$this->renderTemplate('market/settings/ordertypes/_edit', $variables);
	}

    /**
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
	public function actionSaveOrderType()
	{
		$this->requirePostRequest();

		$orderType = new Market_OrderTypeModel();

		// Shared attributes
		$orderType->id                           = craft()->request->getPost('orderTypeId');
		$orderType->name                         = craft()->request->getPost('name');
		$orderType->handle                       = craft()->request->getPost('handle');
		$orderType->shippingMethodId             = craft()->request->getPost('shippingMethodId');
		
		// Set the field layout
		$fieldLayout       = craft()->fields->assembleLayoutFromPost();
		$fieldLayout->type = 'Market_Order';
		$orderType->setFieldLayout($fieldLayout);

		// Save it
		if (craft()->market_orderType->save($orderType)) {
			craft()->userSession->setNotice(Craft::t('Order type saved.'));
			$this->redirectToPostedUrl($orderType);
		} else {
			craft()->userSession->setError(Craft::t('Couldn’t save order type.'));
		}

		// Send the calendar back to the template
		craft()->urlManager->setRouteVariables(['orderType' => $orderType]);
	}

    /**
     * @throws HttpException
     * @throws \Exception
     */
	public function actionDeleteOrderType()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$orderTypeId = craft()->request->getRequiredPost('id');

		craft()->market_orderType->deleteById($orderTypeId);
		$this->returnJson(['success' => true]);
	}

} 