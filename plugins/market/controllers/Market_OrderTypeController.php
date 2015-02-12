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
	protected $allowAnonymous = false;

	public function actionIndex()
	{
		$orderTypes = craft()->market_orderType->getAll();
		$this->renderTemplate('market/settings/ordertypes/index', compact('orderTypes'));

	}

	public function actionEditOrderType(array $variables = array())
	{
		$variables['brandNewOrderType'] = false;

		if (empty($variables['orderType'])) {
			if (!empty($variables['orderTypeId'])) {
				$orderTypeId            = $variables['orderTypeId'];
				$variables['orderType'] = craft()->market_orderType->getById($orderTypeId);

				if (!$variables['orderType']) {
					throw new HttpException(404);
				}
			} else {
				$variables['orderType']         = new Market_OrderTypeModel();
				$variables['brandNewOrderType'] = true;
			};
		}

		if (!empty($variables['orderTypeId'])) {
			$variables['title'] = $variables['orderType']->name;
		} else {
			$variables['title'] = Craft::t('Create a Order Type');
		}

		$this->renderTemplate('market/settings/ordertypes/_edit', $variables);
	}

	public function actionSaveOrderType()
	{
		$this->requirePostRequest();

		$orderType = new Market_OrderTypeModel();

		// Shared attributes
		$orderType->id     = craft()->request->getPost('orderTypeId');
		$orderType->name   = craft()->request->getPost('name');
		$orderType->handle = craft()->request->getPost('handle');

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
		craft()->urlManager->setRouteVariables(array(
			'orderType' => $orderType
		));
	}


	public function actionDeleteOrderType()
	{
		$this->requirePostRequest();
		$this->requireAjaxRequest();

		$orderTypeId = craft()->request->getRequiredPost('id');

		craft()->market_orderType->deleteById($orderTypeId);
		$this->returnJson(array('success' => true));
	}

} 