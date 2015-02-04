<?php
namespace Craft;

use Stripey\Order\Creator;

/**
 * Class Stripey_OrderController
 *
 * @package Craft
 */
class Stripey_OrderController extends Stripey_BaseController
{
	protected $allowAnonymous = false;

	/**
	 * Index of orders
	 */
	public function actionOrderIndex()
	{
		$variables['orderTypes'] = craft()->stripey_orderType->getAll();
		$this->renderTemplate('stripey/orders/_index', $variables);
	}

	public function actionEditOrder(array $variables = array())
	{
		if (!empty($variables['orderTypeHandle'])) {
			$variables['orderType'] = craft()->stripey_orderType->getByHandle($variables['orderTypeHandle']);
		}

		if (empty($variables['orderType'])) {
			throw new HttpException(400, craft::t('Wrong order type specified'));
		}

		if (empty($variables['order'])) {
			if (!empty($variables['orderId'])) {
				$variables['order'] = craft()->stripey_order->getById($variables['orderId']);

				if (!$variables['order']) {
					throw new HttpException(404);
				}
			} else {
				$variables['order']         = new Stripey_OrderModel();
				$variables['order']->typeId = $variables['orderType']->id;
			};
		}

		if (!empty($variables['orderId'])) {
			$variables['title'] = $variables['order']->title;
		} else {
			$variables['title'] = Craft::t('Create a new Order');
		}
		$this->prepVariables($variables);

		$this->renderTemplate('stripey/orders/_edit', $variables);
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

		$orderCreator = new Creator;

		if ($orderCreator->save($order)) {
			$this->redirectToPostedUrl($order);
		}

		craft()->userSession->setNotice(Craft::t("Couldn't save order."));
		craft()->urlManager->setRouteVariables(array(
			'order' => $order
		));
	}

	/**
	 * @return Stripey_OrderModel
	 * @throws Exception
	 */
	private function _setOrderFromPost()
	{
		$orderId = craft()->request->getPost('orderId');

		if ($orderId) {
			$order = craft()->stripey_order->getById($orderId);

			if (!$order) {
				throw new Exception(Craft::t('No order with the ID “{id}”', array('id' => $orderId)));
			}
		} else {
			$order = new Stripey_OrderModel();
		}

		$order->typeId = craft()->request->getPost('typeId');

		return $order;
	}

	/**
	 * @param Stripey_OrderModel $order
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
		$order   = craft()->stripey_order->getById($orderId);

		if (!$order) {
			throw new Exception(Craft::t('No order exists with the ID “{id}”.', array('id' => $orderId)));
		}

		if (craft()->stripey_order->delete($order)) {
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

	/**
	 * @param Stripey_OrderModel $order
	 *
	 * @return Stripey_VariantModel
	 */
	private function _setMasterVariantFromPost($order)
	{
		$attributes = craft()->request->getPost('masterVariant');

		$masterVariant = $order->masterVariant;
		$masterVariant->setAttributes($attributes);
		$masterVariant->isMaster = true;

		return $masterVariant;
	}

} 