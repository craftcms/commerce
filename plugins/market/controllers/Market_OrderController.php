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
class Market_OrderController extends Market_BaseController
{
	protected $allowAnonymous = false;

	/**
	 * Index of orders
	 */
	public function actionOrderIndex()
	{
		$variables['orderTypes'] = craft()->market_orderType->getAll();
		$this->renderTemplate('market/orders/_index', $variables);
	}

	public function actionEditOrder(array $variables = array())
	{
		if (!empty($variables['orderTypeHandle'])) {
			$variables['orderType'] = craft()->market_orderType->getByHandle($variables['orderTypeHandle']);
		}

		if (empty($variables['orderType'])) {
			throw new HttpException(400, craft::t('Wrong order type specified'));
		}

		if (empty($variables['order'])) {
			if (!empty($variables['orderId'])) {
				$variables['order'] = craft()->market_order->getById($variables['orderId']);

				if (!$variables['order']) {
					throw new HttpException(404);
				}
			} else {
				$variables['order']         = new Market_OrderModel();
				$variables['order']->typeId = $variables['orderType']->id;
			};
		}

		if (!empty($variables['orderId'])) {
			$variables['title'] = "Order ".$variables['order']->number;
		} else {
			$variables['title'] = Craft::t('Create a new Order');
		}
		$this->prepVariables($variables);

		$this->renderTemplate('market/orders/_edit', $variables);
	}

	/**
	 * Modifies the variables of the request.
	 *
	 * @param $variables
	 */
	private function prepVariables(&$variables)
	{
		$variables['tabs'] = array();

		foreach ($variables['orderType']->getFieldLayout()->getTabs() as $index => $tab) {
			// Do any of the fields on this tab have errors?
			$hasErrors = false;

			if ($variables['order']->hasErrors()) {
				foreach ($tab->getFields() as $field) {
					if ($variables['order']->getErrors($field->getField()->handle)) {
						$hasErrors = true;
						break;
					}
				}
			}

			$variables['tabs'][] = array(
				'label' => Craft::t($tab->name),
				'url'   => '#tab' . ($index + 1),
				'class' => ($hasErrors ? 'error' : NULL)
			);
		}
	}

	public function actionSaveOrder()
	{
		$this->requirePostRequest();

		$order = $this->_setOrderFromPost();
		$this->_setContentFromPost($order);

		if (craft()->market_order->save($order)) {
			$this->redirectToPostedUrl($order);
		}

		craft()->userSession->setNotice(Craft::t("Couldn't save order."));
		craft()->urlManager->setRouteVariables(array(
			'order' => $order
		));
	}

	/**
	 * @return Market_OrderModel
	 * @throws Exception
	 */
	private function _setOrderFromPost()
	{
		$orderId = craft()->request->getPost('orderId');

		if ($orderId) {
			$order = craft()->market_order->getById($orderId);

			if (!$order) {
				throw new Exception(Craft::t('No order with the ID “{id}”', array('id' => $orderId)));
			}
		} else {
			$order = new Market_OrderModel();
		}

		$order->typeId = craft()->request->getPost('typeId');

		return $order;
	}

	/**
	 * @param Market_OrderModel $order
	 */
	private function _setContentFromPost($order)
	{
		$order->setContentFromPost('fields');
	}

	/**
	 * Deletes a order.
	 *
	 * @throws Exception if you try to edit a non existing Id.
	 */
	public function actionDeleteOrder()
	{
		$this->requirePostRequest();

		$orderId = craft()->request->getRequiredPost('orderId');
		$order   = craft()->market_order->getById($orderId);

		if (!$order) {
			throw new Exception(Craft::t('No order exists with the ID “{id}”.', array('id' => $orderId)));
		}

		if (craft()->market_order->delete($order)) {
			if (craft()->request->isAjaxRequest()) {
				$this->returnJson(array('success' => true));
			} else {
				craft()->userSession->setNotice(Craft::t('Order deleted.'));
				$this->redirectToPostedUrl($order);
			}
		} else {
			if (craft()->request->isAjaxRequest()) {
				$this->returnJson(array('success' => false));
			} else {
				craft()->userSession->setError(Craft::t('Couldn’t delete order.'));

				craft()->urlManager->setRouteVariables(array(
					'order' => $order

				));
			}
		}
	}
}