<?php
namespace Craft;

/**
 * Class Market_CartController
 *
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
		$qty       = craft()->request->getPost('qty', 0);

		if (craft()->market_cart->addToCart($variantId, $qty, $error)) {
			craft()->userSession->setFlash('market', 'Product has been added');
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
		$qty        = craft()->request->getPost('qty', 0);

		if (craft()->market_lineItem->updateQty($lineItemId, $qty, $error)) {
			craft()->userSession->setFlash('market', 'Product quantity has been updated');
			$this->redirectToPostedUrl();
		} else {
			craft()->urlManager->setRouteVariables(['error' => $error]);
		}
	}

	/**
	 * @throws HttpException
	 */
	public function actionApplyCoupon()
	{
		$this->requirePostRequest();

		$code = craft()->request->getPost('couponCode');

		if (craft()->market_cart->applyCoupon($code, $error)) {
			craft()->userSession->setFlash('market', 'Coupon has been applied');
			$this->redirectToPostedUrl();
		} else {
			craft()->urlManager->setRouteVariables(['couponError' => $error]);
		}
	}

	/**
	 * Remove Line item from the cart
	 */
	public function actionRemove()
	{
		$this->requirePostRequest();

		$lineItemId = craft()->request->getPost('lineItemId');

		craft()->market_cart->removeFromCart($lineItemId);
		craft()->userSession->setFlash('market', 'Product has been removed');
		$this->redirectToPostedUrl();
	}

	/**
	 * Remove all line items from the cart
	 */
	public function actionRemoveAll()
	{
		$this->requirePostRequest();

		craft()->market_cart->clearCart();
		craft()->userSession->setFlash('market', 'All products have been removed');
		$this->redirectToPostedUrl();
	}

	/**
	 * @throws Exception
	 */
	public function actionGoToAddress()
	{
		$this->requirePostRequest();

		$order = craft()->market_cart->getCart();
		if (empty($order->lineItems)) {
			craft()->userSession->setNotice(Craft::t('Please add some items to your cart'));

			return;
		}

		if ($order->canTransit(Market_OrderRecord::STATE_ADDRESS)) {
			$order->transition(Market_OrderRecord::STATE_ADDRESS);
			$this->redirectToPostedUrl();
		} else {
			throw new Exception('unable to go to address state from the state: ' . $order->state);
		}
	}

	public function actionGotoCart()
	{
		$this->requirePostRequest();

		$order = craft()->market_cart->getCart();

		if ($order->canTransit(Market_OrderRecord::STATE_CART)) {
			$order->transition(Market_OrderRecord::STATE_CART);
			$this->redirectToPostedUrl();
		} else {
			throw new Exception('unable to go to address state from the state: ' . $order->state);
		}
	}
}