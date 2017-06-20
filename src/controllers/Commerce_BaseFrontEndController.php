<?php
namespace Craft;

/**
 * Class BaseFrontEndController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_BaseFrontEndController extends Commerce_BaseController
{
    protected $allowAnonymous = true;

    /**
     * @param Commerce_OrderModel $cart
     *
     * @return array
     */
    protected function cartArray(Commerce_OrderModel $cart)
    {
        $data = [];
        $data['id'] = $cart->id;
        $data['number'] = $cart->number;
        $data['couponCode'] = $cart->couponCode;
        $data['itemTotal'] = $cart->itemTotal;
        $data['baseDiscount'] = $cart->baseDiscount;
        $data['baseShippingCost'] = $cart->baseShippingCost;
        $data['baseTax'] = $cart->baseTax;
        $data['baseTaxIncluded'] = $cart->baseTaxIncluded;
        $data['totalPrice'] = $cart->totalPrice;
        $data['totalPaid'] = $cart->totalPaid;
        $data['email'] = $cart->email;
        $data['isCompleted'] = $cart->isCompleted;
        $data['dateOrdered'] = $cart->dateOrdered;
        $data['datePaid'] = $cart->datePaid;
        $data['currency'] = $cart->currency;
        $data['paymentCurrency'] = $cart->paymentCurrency;
        $data['lastIp'] = $cart->lastIp;
        $data['message'] = $cart->message;
        $data['returnUrl'] = $cart->returnUrl;
        $data['cancelUrl'] = $cart->cancelUrl;
        $data['orderStatusId'] = $cart->orderStatusId;
        $data['shippingMethod'] = $cart->getShippingMethodHandle();
        $data['shippingMethodId'] = $cart->getShippingMethodId();
        $data['paymentMethodId'] = $cart->paymentMethodId;
        $data['customerId'] = $cart->customerId;
        $data['isPaid'] = $cart->isPaid();
        $data['totalQty'] = $cart->totalQty;
        $data['pdfUrl'] = $cart->getPdfUrl() ? $cart->getPdfUrl('ajax') : '';
        $data['isEmpty'] = $cart->isEmpty();
        $data['itemSubtotal'] = $cart->getItemSubtotal();
        $data['totalWeight'] = $cart->totalWeight;
        $data['totalWidth'] = $cart->totalWidth;
        $data['totalHeight'] = $cart->totalHeight;
        $data['totalLength'] = $cart->totalLength;
        $data['totalTax'] = $cart->getTotalTax();
        $data['totalTaxIncluded'] = $cart->getTotalTaxIncluded();
        $data['totalShippingCost'] = $cart->getTotalShippingCost();
        $data['totalDiscount'] = $cart->getTotalDiscount();

        $data['availableShippingMethods'] = craft()->commerce_shippingMethods->getOrderedAvailableShippingMethods($cart);

        $data['shippingAddressId'] = $cart->shippingAddressId;
        if ($cart->getShippingAddress()){
            $data['shippingAddress'] = $cart->shippingAddress->attributes;
        }else{
            $data['shippingAddress'] = null;
        }

        $data['billingAddressId'] = $cart->billingAddressId;
        if ($cart->getBillingAddress()){
            $data['billingAddress'] = $cart->billingAddress->attributes;
        }else{
            $data['billingAddress'] = null;
        }

        $lineItems = [];
        foreach ($cart->lineItems as $lineItem) {
            $lineItemData = [];
            $lineItemData['id'] = $lineItem->id;
            $lineItemData['price'] = $lineItem->price;
            $lineItemData['saleAmount'] = $lineItem->saleAmount;
            $lineItemData['salePrice'] = $lineItem->salePrice;
            $lineItemData['qty'] = $lineItem->qty;
            $lineItemData['subtotal'] = $lineItem->getSubtotal();
            $lineItemData['tax'] = $lineItem->tax;
            $lineItemData['shippingCost'] = $lineItem->shippingCost;
            $lineItemData['discount'] = $lineItem->discount;
            $lineItemData['total'] = $lineItem->total;
            $lineItemData['weight'] = $lineItem->weight;
            $lineItemData['length'] = $lineItem->length;
            $lineItemData['height'] = $lineItem->height;
            $lineItemData['width'] = $lineItem->width;
            $lineItemData['total'] = $lineItem->total;
            $lineItemData['qty'] = $lineItem->qty;
            $lineItemData['snapshot'] = $lineItem->snapshot;
            $lineItemData['note'] = $lineItem->note;
            $lineItemData['orderId'] = $lineItem->orderId;
            $lineItemData['purchasableId'] = $lineItem->purchasableId;
            $lineItemData['taxCategoryId'] = $lineItem->taxCategoryId;
            $lineItemData['shippingCategoryId'] = $lineItem->shippingCategoryId;
            $lineItemData['onSale'] = $lineItem->getOnSale();
            $lineItemData['options'] = $lineItem->options;
            $lineItemData['optionsSignature'] = $lineItem->optionsSignature;
            $lineItems[$lineItem->id] = $lineItemData;
        }
        $data['lineItems'] = $lineItems;
        $data['totalLineItems'] = count($lineItems);

        $adjustments = [];
        foreach ($cart->adjustments as $adjustment) {
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

        if ($cart->getErrors())
        {
            $data['errors'] = $cart->getErrors();
        }

        // remove un-needed base element attributes
        $remove = ['archived', 'cancelUrl', 'lft', 'level', 'rgt', 'slug', 'uri', 'root'];
        foreach ($remove as $r) {
            unset($data[$r]);
        }

        return $data;
    }
}
