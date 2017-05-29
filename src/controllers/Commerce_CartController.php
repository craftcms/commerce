<?php
namespace Craft;

/**
 * Class Commerce_CartController
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class Commerce_CartController extends Commerce_BaseFrontEndController
{
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
        $qty = craft()->request->getPost('qty');
        $note = craft()->request->getPost('note');

        $cart->setContentFromPost('fields');

        $lineItem = null;
        foreach ($cart->getLineItems() as $item)
        {
            if ($item->id == $lineItemId)
            {
                $lineItem = $item;
                break;
            }
        }

        // Fail silently if its not their line item or it doesn't exist.
        if (!$lineItem || !$lineItem->id || ($cart->id != $lineItem->orderId))
        {
            if (craft()->request->isAjaxRequest)
            {
                $this->returnJson(['success' => true, 'cart' => $this->cartArray($cart)]);
            }
            $this->redirectToPostedUrl();
        }

        // Only update if it was provided in the POST data
        $lineItem->qty = ($qty === null) ? $lineItem->qty : (int) $qty;
        $lineItem->note = ($note === null) ? $lineItem->note : $note;

        // If the options param exists, set it
        if (!is_null(craft()->request->getPost('options')))
        {
            $options = craft()->request->getPost('options', []);
            ksort($options);
            $lineItem->options = $options;
            $lineItem->optionsSignature = md5(json_encode($options));
        }

        if (craft()->commerce_lineItems->updateLineItem($cart, $lineItem, $error))
        {
            craft()->userSession->setNotice(Craft::t('Line item updated.'));
            if (craft()->request->isAjaxRequest)
            {
                $this->returnJson(['success' => true, 'cart' => $this->cartArray($cart)]);
            }
            $this->redirectToPostedUrl();
        }
        else
        {
            if (craft()->request->isAjaxRequest)
            {
                $this->returnErrorJson($error);
            }
            else
            {
                if ($error)
                {
                    craft()->userSession->setError(Craft::t('Couldn’t update line item: {message}', ['message' => $error]));
                }
                else
                {
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

        $cart->setContentFromPost('fields');

        if (craft()->commerce_cart->removeFromCart($cart, $lineItemId))
        {
            if (craft()->request->isAjaxRequest)
            {
                $this->returnJson(['success' => true, 'cart' => $this->cartArray($cart)]);
            }
            craft()->userSession->setNotice(Craft::t('Line item removed.'));
            $this->redirectToPostedUrl();
        }
        else
        {
            $message = Craft::t('Could not remove from line item.');
            if (craft()->request->isAjaxRequest)
            {
                $this->returnErrorJson($message);
            }
            craft()->userSession->setError($message);
        }
    }

    /**
     * Remove all line items from the cart
     */
    public function actionRemoveAllLineItems()
    {
        $this->requirePostRequest();

        $cart = craft()->commerce_cart->getCart();

        $cart->setContentFromPost('fields');

        craft()->commerce_cart->clearCart($cart);
        if (craft()->request->isAjaxRequest)
        {
            $this->returnJson(['success' => true, 'cart' => $this->cartArray($cart)]);
        }
        craft()->userSession->setNotice(Craft::t('Line items removed.'));
        $this->redirectToPostedUrl();
    }

    /**
     * Updates the cart with optional params.
     *
     */
    public function actionUpdateCart()
    {

        $this->requirePostRequest();

        $cart = craft()->commerce_cart->getCart();

        $cart->setContentFromPost('fields');

        $cartSaved = false;

        $sameAddress = craft()->request->getParam('sameAddress');

        $updateErrors = [];

        if (!is_null(craft()->request->getParam('purchasableId')))
        {
            $purchasableId = craft()->request->getPost('purchasableId');
            $note = craft()->request->getPost('note', "");
            $options = craft()->request->getPost('options', []);
            $qty = craft()->request->getPost('qty', 1);
            $error = '';
            if (!craft()->commerce_cart->addToCart($cart, $purchasableId, $qty, $note, $options, $error))
            {
                $addToCartError = Craft::t('Could not add to cart: {error}', [
                    'error' => $error,
                ]);
                $updateErrors['lineItems'] = $addToCartError;
            }
            else
            {
                $cartSaved = true;
            }
        }

        // Set Addresses
        if (!is_null(craft()->request->getParam('shippingAddressId')) && is_numeric(craft()->request->getParam('shippingAddressId')))
        {
            $error = '';
            if ($shippingAddressId = craft()->request->getParam('shippingAddressId'))
            {
                if ($shippingAddress = craft()->commerce_addresses->getAddressById($shippingAddressId))
                {
                    if (!$sameAddress)
                    {
                        if ($billingAddressId = craft()->request->getParam('billingAddressId'))
                        {
                            if ($billingAddress = craft()->commerce_addresses->getAddressById($billingAddressId))
                            {
                                if (!craft()->commerce_orders->setOrderAddresses($cart, $shippingAddress, $billingAddress, $error))
                                {
                                    $updateErrors['addresses'] = $error;
                                }
                                else
                                {
                                    $cartSaved = true;
                                }
                            }
                        }
                        else
                        {
                            $billingAddress = new Commerce_AddressModel();
                            $billingAddress->setAttributes(craft()->request->getParam('billingAddress'));
                            $result = craft()->commerce_orders->setOrderAddresses($cart, $shippingAddress, $billingAddress);
                            if (!$result)
                            {
                                if ($billingAddress->hasErrors())
                                {
                                    $updateErrors['billingAddress'] = Craft::t('Could not save the billing address.');
                                }
                            }
                            else
                            {
                                $cartSaved = true;
                            }
                        }
                    }
                    else
                    {
                        if (!craft()->commerce_orders->setOrderAddresses($cart, $shippingAddress, $shippingAddress))
                        {
                            $updateErrors['shippingAddress'] = Craft::t('Could not save the shipping address.');
                        }
                        else
                        {
                            $cartSaved = true;
                        }
                    }
                }else{
                    $updateErrors['shippingAddressId'] = Craft::t('No shipping address found with that ID.');
                }
            };
        }
        elseif (!is_null(craft()->request->getParam('shippingAddress')))
        {
            $shippingAddress = new Commerce_AddressModel();
            $shippingAddress->setAttributes(craft()->request->getParam('shippingAddress'));
            if (!$sameAddress)
            {
                $billingAddressId = craft()->request->getParam('billingAddressId');
                $billingAddress = craft()->commerce_addresses->getAddressById($billingAddressId);
                if (!$billingAddress)   
                {
                    $billingAddress = new Commerce_AddressModel();
                    $billingAddress->setAttributes(craft()->request->getParam('billingAddress'));
                }

                $result = craft()->commerce_orders->setOrderAddresses($cart, $shippingAddress, $billingAddress);
            }
            else
            {
                $result = craft()->commerce_orders->setOrderAddresses($cart, $shippingAddress, $shippingAddress);
            }
            if (!$result)
            {
                if ($sameAddress)
                {
                    if ($shippingAddress->hasErrors())
                    {
                        $updateErrors['shippingAddress'] = Craft::t('Could not save the shipping address.');
                    }
                }
                else
                {
                    if ($billingAddress->hasErrors())
                    {
                        $updateErrors['billingAddress'] = Craft::t('Could not save the billing address.');
                    }
                }
            }
            else
            {
                $cartSaved = true;
            }
        }

        // Set guest email address onto guest customer and order.
        if (craft()->userSession->isGuest)
        {
            if (!is_null(craft()->request->getParam('email')))
            {
                $error = '';
                $email = craft()->request->getParam('email'); // empty string vs null (strict type checking)
                if (!craft()->commerce_cart->setEmail($cart, $email, $error))
                {
                    $updateErrors['email'] = $error;
                }
                else
                {
                    $cartSaved = true;
                }
            }
        }

        // Set guest email address onto guest customer and order.
        if (!is_null(craft()->request->getParam('paymentCurrency')))
        {
            $currency = craft()->request->getParam('paymentCurrency'); // empty string vs null (strict type checking)
            $error = '';
            if (!craft()->commerce_cart->setPaymentCurrency($cart, $currency, $error))
            {
                $updateErrors['paymentCurrency'] = $error;
            }
            else
            {
                $cartSaved = true;
            }
        }

        // Set Coupon on Cart.
        if (!is_null(craft()->request->getParam('couponCode')))
        {
            $error = '';
            $couponCode = craft()->request->getParam('couponCode');
            if (!craft()->commerce_cart->applyCoupon($cart, $couponCode, $error))
            {
                $updateErrors['couponCode'] = $error;
            }
            else
            {
                $cartSaved = true;
            }
        }

        // Set Payment Method on Cart.
        if (!is_null(craft()->request->getParam('paymentMethodId')))
        {
            $error = '';
            $paymentMethodId = craft()->request->getParam('paymentMethodId');
            if (!craft()->commerce_cart->setPaymentMethod($cart, $paymentMethodId, $error))
            {
                $updateErrors['paymentMethodId'] = $error;
            }
            else
            {
                $cartSaved = true;
            }
        }

        // Set Shipping Method on Cart.
        if (!is_null(craft()->request->getParam('shippingMethod')))
        {
            $error = '';
            $shippingMethod = craft()->request->getParam('shippingMethod');
            if (!craft()->commerce_cart->setShippingMethod($cart, $shippingMethod, $error))
            {
                $updateErrors['shippingMethod'] = $error;
            }
            else
            {
                $cartSaved = true;
            }
        }

        // If they had fields in the post data, but nothing else made the cart save, save the custom fields manually.
        if (!is_null(craft()->request->getParam('fields')) && !$cartSaved)
        {
            craft()->commerce_orders->saveOrder($cart);
        }

        // Clean up error array
        $updateErrors = array_filter($updateErrors);

        if (empty($updateErrors))
        {
            craft()->userSession->setNotice(Craft::t('Cart updated.'));
            if (craft()->request->isAjaxRequest)
            {
                $this->returnJson(['success' => true, 'cart' => $this->cartArray($cart)]);
            }
            $this->redirectToPostedUrl();
        }
        else
        {
            $error = Craft::t('Cart not completely updated.');
            $cart->addErrors($updateErrors);

            if (craft()->request->isAjaxRequest)
            {
                $this->returnJson(['error' => $error, 'cart' => $this->cartArray($cart)]);
            }
            else
            {
                craft()->userSession->setError($error);
            }
        }
    }
}
