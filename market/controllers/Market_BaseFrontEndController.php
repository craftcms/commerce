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
	protected function cartArray (Market_OrderModel $cart)
	{
		$data = [];
		$data['id'] = $cart->id;
		$data['number'] = $cart->number;
		$data['couponCode'] = $cart->couponCode;
		$data['itemTotal'] = $cart->itemTotal;
		$data['baseDiscount'] = $cart->baseDiscount;
		$data['baseShippingCost'] = $cart->baseShippingCost;
		$data['totalPrice'] = $cart->totalPrice;
		$data['totalPaid'] = $cart->totalPaid;
		$data['email'] = $cart->email;
		$data['dateOrdered'] = $cart->dateOrdered;
		$data['datePaid'] = $cart->datePaid;
		$data['currency'] = $cart->currency;
		$data['lastIp'] = $cart->lastIp;
		$data['message'] = $cart->message;
		$data['returnUrl'] = $cart->returnUrl;
		$data['cancelUrl'] = $cart->cancelUrl;
		$data['orderStatusId'] = $cart->orderStatusId;
		$data['billingAddressId'] = $cart->billingAddressId;
		$data['shippingAddressId'] = $cart->shippingAddressId;
		$data['shippingMethodId'] = $cart->shippingMethodId;
		$data['paymentMethodId'] = $cart->paymentMethodId;
		$data['customerId'] = $cart->customerId;
		$data['typeId'] = $cart->typeId;
		$data['shippingAddressData'] = $cart->shippingAddressData;
		$data['billingAddressData'] = $cart->billingAddressData;
		$data['isPaid'] = $cart->isPaid();
		$data['totalQty'] = $cart->totalQty;
		$data['pdfUrl'] = $cart->getPdfUrl('ajax');
		$data['isEmpty'] = $cart->isEmpty();
		$data['totalWeight'] = $cart->totalWeight;
		$data['totalWidth'] = $cart->totalWidth;
		$data['totalHeight'] = $cart->totalHeight;
		$data['totalHeight'] = $cart->totalLength;
		$data['totalTax'] = $cart->totalTax;
		$data['totalShippingCost'] = $cart->totalShippingCost;

		$lineItems = [];
		foreach ($cart->lineItems as $lineItem)
		{
			$lineItemData = [];
			$lineItemData['id'] = $lineItem->id;
			$lineItemData['price'] = $lineItem->price;
			$lineItemData['saleAmount'] = $lineItem->saleAmount;
			$lineItemData['salePrice'] = $lineItem->salePrice;
			$lineItemData['tax'] = $lineItem->tax;
			$lineItemData['shippingCost'] = $lineItem->shippingCost;
			$lineItemData['discount'] = $lineItem->discount;
			$lineItemData['weight'] = $lineItem->weight;
			$lineItemData['length'] = $lineItem->length;
			$lineItemData['height'] = $lineItem->height;
			$lineItemData['width'] = $lineItem->width;
			$lineItemData['total'] = $lineItem->total;
			$lineItemData['qty'] = $lineItem->qty;
			$lineItemData['snapshot'] = $lineItem->snapshot;
			$lineItemData['note'] = $lineItem->note;
			$lineItemData['purchasableId'] = $lineItem->purchasableId;
			$lineItemData['orderId'] = $lineItem->orderId;
			$lineItemData['taxCategoryId'] = $lineItem->taxCategoryId;
			$lineItemData['onSale'] = $lineItem->getOnSale();
			$lineItems[$lineItem->id] = $lineItemData;
		}
		$data['lineItems'] = $lineItems;
		$data['totalLineItems'] = count($lineItems);

		$adjustments = [];
		foreach ($cart->adjustments as $adjustment)
		{
			$adjustmentData = [];
			$adjustmentData['id'] = $adjustment->id;
			$adjustmentData['type'] = $adjustment->type;
			$adjustmentData['name'] = $adjustment->name;
			$adjustmentData['description'] = $adjustment->description;
			$adjustmentData['amount'] = $adjustment->amount;
			$adjustmentData['optionsJson'] = $adjustment->optionsJson;
			$adjustmentData['orderId'] = $adjustment->orderId;
			$adjustments[$adjustment->type][] = $adjustmentData;
		}
		$data['adjustments'] = $adjustments;
		$data['totalAdjustments'] = count($adjustments);

		// remove un-needed base element attributes
		$remove = ['archived', 'cancelUrl', 'lft', 'level', 'rgt', 'slug', 'uri', 'root'];
		foreach ($remove as $r)
		{
			unset($data[$r]);
		}

		return $data;
	}
}