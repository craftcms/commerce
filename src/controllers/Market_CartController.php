<?php
namespace Craft;

/**
 * Class Market_CartController
 * @package Craft
 */
class Market_CartController extends Market_BaseController
{

	public function actionAdd()
	{
		$this->requirePostRequest();

		$variantId = craft()->request->getPost('variantId');
		$qty = craft()->request->getPost('qty');
		$qty *= 1;

		$variant = craft()->market_variant->getById($variantId);

		$error = '';
		if(!$variant) {
			$error = 'wrong variant';
		} elseif ($qty <= 0) {
			$error = 'qty must be above zero';
		} elseif($variant->stock !== null && $qty > $variant->stock) {
			$error = sprintf('There are only %d items left in stock', $variant->stock);
		} elseif ($qty < $variant->minQty) {
			$error = sprintf('Minimal order qty for this variant is %d', $variant->minQty);
		} else {
			$this->redirectToPostedUrl();
		}

		craft()->urlManager->setRouteVariables(['error' => $error]);
	}
}