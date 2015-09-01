<?php
namespace Craft;

/**
 * Class BaseFrontEndController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com/commerce
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Market_BaseFrontEndController extends Market_BaseController
{
	/**
	 * @param Market_OrderModel $cart
	 *
	 * @return array
	 */
	protected function cartArray(Market_OrderModel $cart)
	{
		$data = [];

		foreach($cart->defineAttributes() as $key => $val){
			$data[$key] = $cart->getAttribute($key, true);
		}
		$data['isPaid'] = $cart->isPaid();
		$data['totalQty'] = $cart->totalQty;
		$data['pdfUrl'] = $cart->getPdfUrl('ajax');
		$data['isEmpty'] = $cart->isEmpty();
		$data['totalWeight'] = $cart->totalWeight;
		$data['totalWidth'] = $cart->totalWidth;
		$data['totalHeight'] = $cart->totalHeight;
		$data['totalHeight'] = $cart->totalLength;

		$lineItems = [];
		foreach($cart->lineItems as $lineItem){
			$lineItemData = [];
			foreach ($lineItem->defineAttributes() as $key => $val) {
				$lineItemData[$key] = $lineItem->getAttribute($key, true);
			}
			$lineItemData['underSale'] = $lineItem->underSale;
			$lineItemData['onSale'] = $lineItem->getOnSale();

			$lineItems[$lineItem->id] = $lineItemData;
		}
		$data['lineItems'] = $lineItems;

		$adjustments = [];
		foreach($cart->adjustments as $adjustments){

			$adjustmentData = [];
			foreach($adjustments->defineAttributes() as $key => $val){
				$adjustmentData[$key] = $adjustments->getAttribute($key, true);
			}
			$lineItems[$adjustments->type][$adjustments->id] = $adjustmentData;
		}
		$data['adjustments'] = $adjustments;

		// remove un-needed base element attributes
		$remove = ['archived','cancelUrl','lft','level','rgt','slug','uri','root'];
		foreach($remove as $r){
			unset($data[$r]);
		}
		return $data;
	}
}