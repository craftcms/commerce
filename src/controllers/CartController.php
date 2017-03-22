<?php
namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use yii\base\Exception;
use yii\web\HttpException;

/**
 * Class Cart Controller
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.controllers
 * @since     1.0
 */
class CartController extends BaseFrontEndController
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

        $cart = Plugin::getInstance()->getCart()->getCart();
        $lineItemId = Craft::$app->getRequest()->getParam('lineItemId');
        $qty = Craft::$app->getRequest()->getParam('qty', 0);
        $note = Craft::$app->getRequest()->getParam('note');

        $cart->setContentFromPost('fields');

        $lineItem = null;
        foreach ($cart->getLineItems() as $item) {
            if ($item->id == $lineItemId) {
                $lineItem = $item;
                break;
            }
        }

        // Fail silently if its not their line item or it doesn't exist.
        if (!$lineItem || !$lineItem->id || ($cart->id != $lineItem->orderId)) {
            if (Craft::$app->getRequest()->isAjax) {
                $this->asJson(['success' => true, 'cart' => $this->cartArray($cart)]);
            }
            $this->redirectToPostedUrl();
        }

        $lineItem->qty = $qty;
        $lineItem->note = $note;

        // If the options param exists, set it
        if (!is_null(Craft::$app->getRequest()->getParam('options'))) {
            $options = Craft::$app->getRequest()->getParam('options', []);
            ksort($options);
            $lineItem->options = $options;
            $lineItem->optionsSignature = md5(json_encode($options));
        }

        if (Plugin::getInstance()->getLineItems()->updateLineItem($cart, $lineItem, $error)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Line item updated.'));
            if (Craft::$app->getRequest()->isAjax) {
                $this->asJson(['success' => true, 'cart' => $this->cartArray($cart)]);
            }
            $this->redirectToPostedUrl();
        } else {
            if (Craft::$app->getRequest()->isAjax) {
                $this->asErrorJson($error);
            } else {
                if ($error) {
                    Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t update line item: {message}', ['message' => $error]));
                } else {
                    Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t update line item.'));
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

        $lineItemId = Craft::$app->getRequest()->getParam('lineItemId');
        $cart = Plugin::getInstance()->getCart()->getCart();

        $cart->setContentFromPost('fields');

        if (Plugin::getInstance()->getCart()->removeFromCart($cart, $lineItemId)) {
            if (Craft::$app->getRequest()->isAjax) {
                $this->asJson(['success' => true, 'cart' => $this->cartArray($cart)]);
            }
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Line item removed.'));
            $this->redirectToPostedUrl();
        } else {
            $message = Craft::t('commerce', 'Could not remove from line item.');
            if (Craft::$app->getRequest()->isAjax) {
                $this->asErrorJson($message);
            }
            Craft::$app->getSession()->setError($message);
        }
    }

    /**
     * Remove all line items from the cart
     */
    public function actionRemoveAllLineItems()
    {
        $this->requirePostRequest();

        $cart = Plugin::getInstance()->getCart()->getCart();

        $cart->setContentFromPost('fields');

        Plugin::getInstance()->getCart()->clearCart($cart);
        if (Craft::$app->getRequest()->isAjax) {
            $this->asJson(['success' => true, 'cart' => $this->cartArray($cart)]);
        }
        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Line items removed.'));
        $this->redirectToPostedUrl();
    }

    /**
     * Updates the cart with optional params.
     *
     */
    public function actionUpdateCart()
    {

        $this->requirePostRequest();

        $cart = Plugin::getInstance()->getCart()->getCart();

        $cart->setContentFromPost('fields');

        $cartSaved = false;

        $sameAddress = Craft::$app->getRequest()->getParam('sameAddress');

        $updateErrors = [];

        if (!is_null(Craft::$app->getRequest()->getParam('purchasableId'))) {
            $purchasableId = Craft::$app->getRequest()->getParam('purchasableId');
            $note = Craft::$app->getRequest()->getParam('note', "");
            $options = Craft::$app->getRequest()->getParam('options', []);
            $qty = Craft::$app->getRequest()->getParam('qty', 1);
            $error = '';
            if (!Plugin::getInstance()->getCart()->addToCart($cart, $purchasableId, $qty, $note, $options, $error)) {
                $addToCartError = Craft::t('commerce', 'Could not add to cart: {error}', [
                    'error' => $error,
                ]);
                $updateErrors['lineItems'] = $addToCartError;
            } else {
                $cartSaved = true;
            }
        }

        // Set Addresses
        if (!is_null(Craft::$app->getRequest()->getParam('shippingAddressId')) && is_numeric(Craft::$app->getRequest()->getParam('shippingAddressId'))) {
            $error = '';
            if ($shippingAddressId = Craft::$app->getRequest()->getParam('shippingAddressId')) {
                if ($shippingAddress = Plugin::getInstance()->getAddresses()->getAddressById($shippingAddressId)) {
                    if (!$sameAddress) {
                        if ($billingAddressId = Craft::$app->getRequest()->getParam('billingAddressId')) {
                            if ($billingAddress = Plugin::getInstance()->getAddresses()->getAddressById($billingAddressId)) {
                                if (!Plugin::getInstance()->getOrders()->setOrderAddresses($cart, $shippingAddress, $billingAddress, $error)) {
                                    $updateErrors['addresses'] = $error;
                                } else {
                                    $cartSaved = true;
                                }
                            }
                        } else {
                            $billingAddress = new Address();
                            $billingAddress->setAttributes(Craft::$app->getRequest()->getParam('billingAddress'));
                            $result = Plugin::getInstance()->getOrders()->setOrderAddresses($cart, $shippingAddress, $billingAddress);
                            if (!$result) {
                                if ($billingAddress->hasErrors()) {
                                    $updateErrors['billingAddress'] = Craft::t('commerce', 'Could not save the billing address.');
                                }
                            } else {
                                $cartSaved = true;
                            }
                        }
                    } else {
                        if (!Plugin::getInstance()->getOrders()->setOrderAddresses($cart, $shippingAddress, $shippingAddress)) {
                            $updateErrors['shippingAddress'] = Craft::t('commerce', 'Could not save the shipping address.');
                        } else {
                            $cartSaved = true;
                        }
                    }
                } else {
                    $updateErrors['shippingAddressId'] = Craft::t('commerce', 'No shipping address found with that ID.');
                }
            };
        } elseif (!is_null(Craft::$app->getRequest()->getParam('shippingAddress'))) {
            $shippingAddress = new Address();
            $shippingAddress->setAttributes(Craft::$app->getRequest()->getParam('shippingAddress'));
            if (!$sameAddress) {
                if ($billingAddressId = Craft::$app->getRequest()->getParam('billingAddressId')) {
                    $billingAddress = Plugin::getInstance()->getAddresses()->getAddressById($billingAddressId);
                } else {
                    $billingAddress = new Address();
                    $billingAddress->setAttributes(Craft::$app->getRequest()->getParam('billingAddress'));
                }

                $result = Plugin::getInstance()->getOrders()->setOrderAddresses($cart, $shippingAddress, $billingAddress);
            } else {
                $result = Plugin::getInstance()->getOrders()->setOrderAddresses($cart, $shippingAddress, $shippingAddress);
            }
            if (!$result) {
                if ($sameAddress) {
                    if ($shippingAddress->hasErrors()) {
                        $updateErrors['shippingAddress'] = Craft::t('commerce', 'Could not save the shipping address.');
                    }
                } else {
                    if ($billingAddress->hasErrors()) {
                        $updateErrors['billingAddress'] = Craft::t('commerce', 'Could not save the billing address.');
                    }
                }
            } else {
                $cartSaved = true;
            }
        }

        // Set guest email address onto guest customer and order.
        if (Craft::$app->getUser()->isGuest) {
            if (!is_null(Craft::$app->getRequest()->getParam('email'))) {
                $error = '';
                $email = Craft::$app->getRequest()->getParam('email'); // empty string vs null (strict type checking)
                if (!Plugin::getInstance()->getCart()->setEmail($cart, $email, $error)) {
                    $updateErrors['email'] = $error;
                } else {
                    $cartSaved = true;
                }
            }
        }

        // Set guest email address onto guest customer and order.
        if (!is_null(Craft::$app->getRequest()->getParam('paymentCurrency'))) {
            $currency = Craft::$app->getRequest()->getParam('paymentCurrency'); // empty string vs null (strict type checking)
            $error = '';
            if (!Plugin::getInstance()->getCart()->setPaymentCurrency($cart, $currency, $error)) {
                $updateErrors['paymentCurrency'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // Set Coupon on Cart.
        if (!is_null(Craft::$app->getRequest()->getParam('couponCode'))) {
            $error = '';
            $couponCode = Craft::$app->getRequest()->getParam('couponCode');
            if (!Plugin::getInstance()->getCart()->applyCoupon($cart, $couponCode, $error)) {
                $updateErrors['couponCode'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // Set Payment Method on Cart.
        if (!is_null(Craft::$app->getRequest()->getParam('paymentMethodId'))) {
            $error = '';
            $paymentMethodId = Craft::$app->getRequest()->getParam('paymentMethodId');
            if (!Plugin::getInstance()->getCart()->setPaymentMethod($cart, $paymentMethodId, $error)) {
                $updateErrors['paymentMethodId'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // Set Shipping Method on Cart.
        if (!is_null(Craft::$app->getRequest()->getParam('shippingMethod'))) {
            $error = '';
            $shippingMethod = Craft::$app->getRequest()->getParam('shippingMethod');
            if (!Plugin::getInstance()->getCart()->setShippingMethod($cart, $shippingMethod, $error)) {
                $updateErrors['shippingMethod'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // If they had fields in the post data, but nothing else made the cart save, save the custom fields manually.
        if (!is_null(Craft::$app->getRequest()->getParam('fields')) && !$cartSaved) {
            Plugin::getInstance()->getOrders()->saveOrder($cart);
        }

        // Clean up error array
        $updateErrors = array_filter($updateErrors);

        if (empty($updateErrors)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Cart updated.'));
            if (Craft::$app->getRequest()->isAjax) {
                $this->asJson(['success' => true, 'cart' => $this->cartArray($cart)]);
            }
            $this->redirectToPostedUrl();
        } else {
            $error = Craft::t('commerce', 'Cart not completely updated.');
            $cart->addErrors($updateErrors);

            if (Craft::$app->getRequest()->isAjax) {
                $this->asJson(['error' => $error, 'cart' => $this->cartArray($cart)]);
            } else {
                Craft::$app->getSession()->setError($error);
            }
        }
    }
}
