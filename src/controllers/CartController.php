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
    private $_cart;

    /**
     * Update quantity
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionUpdateLineItem()
    {
        $this->requirePostRequest();

        $this->_cart = Plugin::getInstance()->getCart()->getCart();
        $lineItemId = Craft::$app->getRequest()->getParam('lineItemId');
        $qty = Craft::$app->getRequest()->getParam('qty', 0);
        $note = Craft::$app->getRequest()->getParam('note');

        $this->_cart->setFieldValuesFromRequest('fields');

        $lineItem = null;
        foreach ($this->_cart->getLineItems() as $item) {
            if ($item->id == $lineItemId) {
                $lineItem = $item;
                break;
            }
        }

        // Fail silently if its not their line item or it doesn't exist.
        if (!$lineItem || !$lineItem->id || ($this->_cart->id != $lineItem->orderId)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => true, 'cart' => $this->cartArray($this->_cart)]);
            }
            $this->redirectToPostedUrl();
        }

        $lineItem->qty = $qty;
        $lineItem->note = $note;

        // If the options param exists, set it
        if (null !== Craft::$app->getRequest()->getParam('options')) {
            $options = Craft::$app->getRequest()->getParam('options', []);
            ksort($options);
            $lineItem->options = $options;
            $lineItem->optionsSignature = md5(json_encode($options));
        }

        if (Plugin::getInstance()->getLineItems()->updateLineItem($this->_cart, $lineItem, $error)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Line item updated.'));
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => true, 'cart' => $this->cartArray($this->_cart)]);
            }
            $this->redirectToPostedUrl();
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
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
        $this->_cart = Plugin::getInstance()->getCart()->getCart();

        $this->_cart->setFieldValuesFromRequest('fields');

        if (Plugin::getInstance()->getCart()->removeFromCart($this->_cart, $lineItemId)) {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => true, 'cart' => $this->cartArray($this->_cart)]);
            }
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Line item removed.'));
            $this->redirectToPostedUrl();
        } else {
            $message = Craft::t('commerce', 'Could not remove from line item.');
            if (Craft::$app->getRequest()->getAcceptsJson()) {
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

        $this->_cart = Plugin::getInstance()->getCart()->getCart();

        $this->_cart->setFieldValuesFromRequest('fields');

        Plugin::getInstance()->getCart()->clearCart($this->_cart);
        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $this->asJson(['success' => true, 'cart' => $this->cartArray($this->_cart)]);
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

        $this->_cart = Plugin::getInstance()->getCart()->getCart();

        $this->_cart->setFieldValuesFromRequest('fields');

        $cartSaved = false;

        $sameAddress = Craft::$app->getRequest()->getParam('sameAddress');

        $updateErrors = [];

        if (null !== Craft::$app->getRequest()->getParam('purchasableId')) {
            $purchasableId = Craft::$app->getRequest()->getParam('purchasableId');
            $note = Craft::$app->getRequest()->getParam('note', "");
            $options = Craft::$app->getRequest()->getParam('options', []);
            $qty = Craft::$app->getRequest()->getParam('qty', 1);
            $error = '';
            if (!Plugin::getInstance()->getCart()->addToCart($this->_cart, $purchasableId, $qty, $note, $options, $error)) {
                $addToCartError = Craft::t('commerce', 'Could not add to cart: {error}', [
                    'error' => $error,
                ]);
                $updateErrors['lineItems'] = $addToCartError;
            } else {
                $cartSaved = true;
            }
        }

        // Set Addresses
        if (null !== Craft::$app->getRequest()->getParam('shippingAddressId') && is_numeric(Craft::$app->getRequest()->getParam('shippingAddressId'))) {
            $error = '';
            if ($shippingAddressId = Craft::$app->getRequest()->getParam('shippingAddressId')) {
                if ($shippingAddress = Plugin::getInstance()->getAddresses()->getAddressById($shippingAddressId)) {
                    if (!$sameAddress) {
                        if ($billingAddressId = Craft::$app->getRequest()->getParam('billingAddressId')) {
                            if ($billingAddress = Plugin::getInstance()->getAddresses()->getAddressById($billingAddressId)) {
                                if (!$this->_setOrderAddresses($shippingAddress, $billingAddress, $error)) {
                                    $updateErrors['addresses'] = $error;
                                } else {
                                    $cartSaved = true;
                                }
                            }
                        } else {
                            $billingAddress = new Address();
                            $billingAddress->setAttributes(Craft::$app->getRequest()->getParam('billingAddress'));
                            $result = $this->_setOrderAddresses($shippingAddress, $billingAddress);
                            if (!$result) {
                                if ($billingAddress->hasErrors()) {
                                    $updateErrors['billingAddress'] = Craft::t('commerce', 'Could not save the billing address.');
                                }
                            } else {
                                $cartSaved = true;
                            }
                        }
                    } else {
                        if (!$this->_setOrderAddresses($shippingAddress, $shippingAddress)) {
                            $updateErrors['shippingAddress'] = Craft::t('commerce', 'Could not save the shipping address.');
                        } else {
                            $cartSaved = true;
                        }
                    }
                } else {
                    $updateErrors['shippingAddressId'] = Craft::t('commerce', 'No shipping address found with that ID.');
                }
            };
        } elseif (null !== Craft::$app->getRequest()->getParam('shippingAddress')) {
            $shippingAddress = new Address();
            $shippingAddress->setAttributes(Craft::$app->getRequest()->getParam('shippingAddress'));
            if (!$sameAddress) {
                if ($billingAddressId = Craft::$app->getRequest()->getParam('billingAddressId')) {
                    $billingAddress = Plugin::getInstance()->getAddresses()->getAddressById($billingAddressId);
                } else {
                    $billingAddress = new Address();
                    $billingAddress->setAttributes(Craft::$app->getRequest()->getParam('billingAddress'));
                }

                $result = $this->_setOrderAddresses($shippingAddress, $billingAddress);
            } else {
                $result = $this->_setOrderAddresses($shippingAddress, $shippingAddress);
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
            if (null !== Craft::$app->getRequest()->getParam('email')) {
                $error = '';
                $email = Craft::$app->getRequest()->getParam('email'); // empty string vs null (strict type checking)
                if (!Plugin::getInstance()->getCart()->setEmail($this->_cart, $email, $error)) {
                    $updateErrors['email'] = $error;
                } else {
                    $cartSaved = true;
                }
            }
        }

        // Set guest email address onto guest customer and order.
        if (null !== Craft::$app->getRequest()->getParam('paymentCurrency')) {
            $currency = Craft::$app->getRequest()->getParam('paymentCurrency'); // empty string vs null (strict type checking)
            $error = '';
            if (!Plugin::getInstance()->getCart()->setPaymentCurrency($this->_cart, $currency, $error)) {
                $updateErrors['paymentCurrency'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // Set Coupon on Cart.
        if (null !== Craft::$app->getRequest()->getParam('couponCode')) {
            $error = '';
            $couponCode = Craft::$app->getRequest()->getParam('couponCode');
            if (!Plugin::getInstance()->getCart()->applyCoupon($this->_cart, $couponCode, $error)) {
                $updateErrors['couponCode'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // Set Payment Method on Cart.
        if (null !== Craft::$app->getRequest()->getParam('paymentMethodId')) {
            $error = '';
            $paymentMethodId = Craft::$app->getRequest()->getParam('paymentMethodId');
            if (!Plugin::getInstance()->getCart()->setPaymentMethod($this->_cart, $paymentMethodId, $error)) {
                $updateErrors['paymentMethodId'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // Set Shipping Method on Cart.
        if (null !== Craft::$app->getRequest()->getParam('shippingMethod')) {
            $error = '';
            $shippingMethod = Craft::$app->getRequest()->getParam('shippingMethod');
            if (!Plugin::getInstance()->getCart()->setShippingMethod($this->_cart, $shippingMethod, $error)) {
                $updateErrors['shippingMethod'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // If they had fields in the post data, but nothing else made the cart save, save the custom fields manually.
        if (null !== Craft::$app->getRequest()->getParam('fields') && !$cartSaved) {
            Craft::$app->getElements()->saveElement($this->_cart);
        }

        // Clean up error array
        $updateErrors = array_filter($updateErrors);

        if (empty($updateErrors)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Cart updated.'));
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['success' => true, 'cart' => $this->cartArray($this->_cart)]);
            }
            $this->redirectToPostedUrl();
        } else {
            $error = Craft::t('commerce', 'Cart not completely updated.');
            $this->_cart->addErrors($updateErrors);

            if (Craft::$app->getRequest()->getAcceptsJson()) {
                $this->asJson(['error' => $error, 'cart' => $this->cartArray($this->_cart)]);
            } else {
                Craft::$app->getSession()->setError($error);
            }
        }
    }

    private function _setOrderAddresses(
        Address $shippingAddress,
        Address $billingAddress,
        &$error = ''
    ) {

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {

            $customerId = $this->_cart->customerId;
            $currentCustomerAddressIds = Plugin::getInstance()->getCustomers()->getAddressIds($customerId);

            $ownAddress = true;
            // Customers can only set addresses that are theirs
            if ($shippingAddress->id && !in_array($shippingAddress->id, $currentCustomerAddressIds)) {
                $ownAddress = false;
            }
            // Customer can only set addresses that are theirs
            if ($billingAddress->id && !in_array($billingAddress->id, $currentCustomerAddressIds)) {
                $ownAddress = false;
            }

            if (!$ownAddress) {
                $error = Craft::t('commerce', 'Can not choose an address ID that does not belong to the customer.');
            }

            $result1 = Plugin::getInstance()->getCustomers()->saveAddress($shippingAddress);

            if (($billingAddress->id && $billingAddress->id == $shippingAddress->id) || $shippingAddress === $billingAddress) {
                $result2 = true;
            } else {
                $result2 = Plugin::getInstance()->getCustomers()->saveAddress($billingAddress);
            }

            $this->_cart->setShippingAddress($shippingAddress);
            $this->_cart->setBillingAddress($billingAddress);

            if ($result1 && $result2) {

                $this->_cart->shippingAddressId = $shippingAddress->id;
                $this->_cart->billingAddressId = $billingAddress->id;

                Craft::$app->getElements()->saveElement($this->_cart);
                $transaction->commit();

                return true;
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $transaction->rollBack();

        return false;
    }
}
