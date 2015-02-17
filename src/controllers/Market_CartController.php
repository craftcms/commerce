<?php
namespace Craft;

/**
 * Class Market_CartController
 * @package Craft
 */
class Market_CartController extends Market_BaseController
{
	public function actionIndex()
	{
		$cart = craft()->market_order->getCart();
		$this->renderTemplate('store/market/cart', compact('cart'));
	}

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
}