<?php
namespace Craft;

/**
 * Class Market_CartController
 * @package Craft
 */
class Market_CartController extends Market_BaseController
{
	protected $allowAnonymous = true;

	/**
	 * Add a product variant into the cart
	 *
	 * @throws Exception
	 * @throws HttpException
	 * @throws \Exception
	 */
	public function actionAdd()
	{
		$this->requirePostRequest();

		$variantId = craft()->request->getPost('variantId');
		$qty = craft()->request->getPost('qty', 0);

		if(craft()->market_order->addToCart($variantId, $qty, $error)) {
			$this->redirectToPostedUrl();
		} else {
			craft()->urlManager->setRouteVariables(['error' => $error]);
		}
	}

	/**
	 * Update quantity
	 *
	 * @throws Exception
	 * @throws HttpException
	 */
	public function actionUpdateQty()
	{
		$this->requirePostRequest();

		$lineItemId = craft()->request->getPost('lineItemId');
		$qty = craft()->request->getPost('qty', 0);

		if(craft()->market_lineItem->updateQty($lineItemId, $qty, $error)) {
			$this->redirectToPostedUrl();
		} else {
			craft()->urlManager->setRouteVariables(['error' => $error]);
		}
	}

	/**
	 * Remove Line item from the cart
	 */
	public function actionRemove()
	{
		$this->requirePostRequest();

		$lineItemId = craft()->request->getPost('lineItemId');

		craft()->market_order->removeFromCart($lineItemId);
		$this->redirectToPostedUrl();
	}

	/**
	 * Remove all line items from the cart
	 */
	public function actionRemoveAll()
	{
		$this->requirePostRequest();

		craft()->market_order->clearCart();
		$this->redirectToPostedUrl();
	}

	/**
	 * @throws Exception
	 */
	public function actionGoToAddress()
	{
		$this->requirePostRequest();

		$order = craft()->market_order->getCart();

		if($order->canTransit(Market_OrderRecord::STATE_ADDRESS)) {
			$order->transition(Market_OrderRecord::STATE_ADDRESS);
			$this->redirectToPostedUrl();
		} else {
			throw new Exception('unable to go to address state from the state: ' . $order->state);
		}
	}
}