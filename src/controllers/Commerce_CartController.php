<?php
namespace Craft;

/**
 * Class Commerce_CartController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   http://craftcommerce.com/license Craft Commerce License Agreement
 * @see       http://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_CartController extends Commerce_BaseFrontEndController
{
    /**
     * Add a purchasable into the cart
     *
     * @throws Exception
     * @throws HttpException
     * @throws \Exception
     */
    public function actionAdd()
    {
        $this->requirePostRequest();

        /** @var Commerce_OrderModel $cart */
        $cart = craft()->commerce_cart->getCart();
        $cart->setContentFromPost('fields');

        $purchasableId = craft()->request->getPost('purchasableId');
        $note = craft()->request->getPost('note');
        $qty = craft()->request->getPost('qty', 1);
        $error = '';

        if (craft()->commerce_cart->addToCart($cart, $purchasableId, $qty, $note, $error)) {
            if (craft()->request->isAjaxRequest) {
                $this->returnJson(['success' => true, 'cart' => $this->cartArray($cart)]);
            }
            craft()->userSession->setNotice(Craft::t('Item added.'));
            $this->redirectToPostedUrl();
        } else {
            if (craft()->request->isAjaxRequest) {
                $this->returnJson(['error' => $error]);
            } else {
                if ($error) {
                    craft()->userSession->setError(Craft::t('Couldn’t add item to cart: {message}', [
                        'message' => $error
                    ]));
                } else {
                    craft()->userSession->setError(Craft::t('Couldn’t add item to cart.'));
                }
            }
        }
    }

    /**
     * Update quantity
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionUpdateLineItem()
    {
        $this->requirePostRequest();

        $cart = craft()->commerce_cart->getCart();
        $lineItemId = craft()->request->getPost('lineItemId');
        $qty = craft()->request->getPost('qty', 0);
        $note = craft()->request->getPost('note');

        $lineItem = craft()->commerce_lineItems->getLineItemById($lineItemId);

        // Error does not reveal the line item doesn't exist, just that it doesn't for the current cart.
        if(!$lineItem){
            throw new Exception(Craft::t('Line item not found in current cart'));
        }

        // Only let them update their own cart's line item.
        if (!$lineItem->id || $cart->id != $lineItem->order->id) {
            throw new Exception(Craft::t('Line item not found in current cart'));
        }

        $lineItem->qty = $qty;
        $lineItem->note = $note;
        $lineItem->order->setContentFromPost('fields');

        if (craft()->commerce_lineItems->updateLineItem($cart, $lineItem, $error)) {
            craft()->userSession->setNotice(Craft::t('Line item updated.'));
            if (craft()->request->isAjaxRequest) {
                $this->returnJson(['success' => true, 'cart' => $this->cartArray($cart)]);
            }
            $this->redirectToPostedUrl();
        } else {
            if (craft()->request->isAjaxRequest) {
                $this->returnErrorJson($error);
            } else {
                if ($error) {
                    craft()->userSession->setError(Craft::t('Couldn’t update lite item: {message}', [
                        'message' => $error
                    ]));
                } else {
                    craft()->userSession->setError(Craft::t('Couldn’t update line item.'));
                }
            }
        }
    }

    /**
     * Remove Line item from the cart
     */
    public function actionRemoveLineItem()
    {
        $this->requirePostRequest();

        $lineItemId = craft()->request->getPost('lineItemId');
        $cart = craft()->commerce_cart->getCart();

        $lineItem = craft()->commerce_lineItems->getLineItemById($lineItemId);

        // Error does not reveal the line item doesn't exist, just that it doesn't for the current cart.
        if(!$lineItem){
            throw new Exception(Craft::t('Line item not found in current cart'));
        }

        // Only let them update their own cart's line item.
        if (!$lineItem->id || $cart->id != $lineItem->orderId) {
            throw new Exception(Craft::t('Line item not found in current cart'));
        }

        craft()->commerce_cart->removeFromCart($cart, $lineItemId);
        if (craft()->request->isAjaxRequest) {
            $this->returnJson(['success' => true, 'cart' => $this->cartArray($cart)]);
        }
        craft()->userSession->setNotice(Craft::t('Line item removed.'));
        $this->redirectToPostedUrl();
    }

    /**
     * Remove all line items from the cart
     */
    public function actionRemoveAllLineItems()
    {
        $this->requirePostRequest();

        $cart = craft()->commerce_cart->getCart();

        craft()->commerce_cart->clearCart($cart);
        if (craft()->request->isAjaxRequest) {
            $this->returnJson(['success' => true, 'cart' => $this->cartArray($cart)]);
        }
        craft()->userSession->setNotice(Craft::t('Line items removed.'));
        $this->redirectToPostedUrl();
    }

    /**
     * Updates the cart with optional params.
     *
     */
    public function actionUpdate()
    {

        $this->requirePostRequest();

        $cart = craft()->commerce_cart->getCart();

        $cart->setContentFromPost('fields');

        $sameAddress = craft()->request->getParam('sameAddress');

        // Set Addresses
        if (isset($_POST['shippingAddressId']) && is_numeric(craft()->request->getParam('shippingAddressId'))) {
            if ($shippingAddressId = craft()->request->getParam('shippingAddressId')) {
                if ($shippingAddress = craft()->commerce_addresses->getAddressById($shippingAddressId)) {

                    if ($sameAddress) {

                    }
                    if (!craft()->commerce_orders->setAddresses($cart, $shippingAddress, $shippingAddress)) {
                        $cart->addError('shippingAddressId', Craft::t('Could not save the shipping address.'));
                    }
                }
            };
        } elseif (isset($_POST['shippingAddress'])) {
            $shippingAddress = new Commerce_AddressModel();
            $shippingAddress->setAttributes(craft()->request->getParam('shippingAddress'));
            if (!$sameAddress) {
                $billingAddress = new Commerce_AddressModel();
                $billingAddress->setAttributes(craft()->request->getParam('billingAddress'));
                $result = craft()->commerce_orders->setAddresses($cart, $shippingAddress, $billingAddress);
            } else {
                $result = craft()->commerce_orders->setAddresses($cart, $shippingAddress, $shippingAddress);
            }
            if (!$result) {
                if ($sameAddress) {
                    if ($shippingAddress->hasErrors()) {
                        $cart->addError('shippingAddress', Craft::t('Could not save the Shipping Address.'));
                    }
                } else {
                    if ($billingAddress->hasErrors()) {
                        $cart->addError('billingAddress', Craft::t('Could not save the Billing Address.'));
                    }
                }
            };
        }

        // Set guest email address onto guest customer and order.
        if (craft()->userSession->isGuest) {
            if (isset($_POST['email'])) {
                $email = craft()->request->getParam('email'); // empty string vs null (strict type checking)
                if (!craft()->commerce_cart->setEmail($cart, $email, $error)) {
                    $cart->addError('email', $error);
                }
            }
        }

        // Set Coupon on Cart.
        if (isset($_POST['couponCode'])) {
            $couponCode = craft()->request->getParam('couponCode');
            if (!craft()->commerce_cart->applyCoupon($cart, $couponCode, $error)) {
                $cart->addError('couponCode', $error);
            }
        }

        // Set Payment Method on Cart.
        if (isset($_POST['paymentMethodId'])) {
            $paymentMethodId = craft()->request->getParam('paymentMethodId');
            if (!craft()->commerce_cart->setPaymentMethod($cart, $paymentMethodId, $error)) {
                $cart->addError('paymentMethodId', $error);
            }
        }

        // Set Shipping Method on Cart.
        if (isset($_POST['shippingMethod'])) {
            $shippingMethod = craft()->request->getParam('shippingMethod');
            if (!craft()->commerce_cart->setShippingMethod($cart, $shippingMethod, $error)) {
                $cart->addError('shippingMethod', $error);
            }
        }

        if (!$cart->hasErrors()) {
            craft()->userSession->setNotice(Craft::t('Cart updated.'));
            if (craft()->request->isAjaxRequest) {
                $this->returnJson(['success' => true, 'cart' => $this->cartArray($cart)]);
            }
            $this->redirectToPostedUrl();
        } else {
            $error = Craft::t('Cart not completely updated.');
            if (craft()->request->isAjaxRequest) {
                $this->returnErrorJson($error);
            } else {
                craft()->userSession->setError($error);
            }
        }
    }
}
