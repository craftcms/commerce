<?php

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use craft\web\Response;
use yii\base\Exception;
use yii\web\HttpException;

/**
 * Class Cart Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CartController extends BaseFrontEndController
{
    // Properties
    // =========================================================================

    /**
     * @var
     */
    private $_cart;

    // Public Methods
    // =========================================================================

    /**
     * Update quantity
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionUpdateLineItem()
    {
        $this->requirePostRequest();

        $this->_cart = Plugin::getInstance()->getCarts()->getCart();
        $lineItemId = Craft::$app->getRequest()->getParam('lineItemId');
        $qty = Craft::$app->getRequest()->getParam('qty');
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
            return $this->redirectToPostedUrl();
        }

        // Only update if it was provided in the POST data
        $lineItem->qty = ($qty === null) ? $lineItem->qty : (int)$qty;
        $lineItem->note = ($note === null) ? $lineItem->note : (string)$note;

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
                return $this->asJson(['success' => true, 'cart' => $this->cartArray($this->_cart)]);
            }
            $this->redirectToPostedUrl();
        } else {
            if (Craft::$app->getRequest()->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }
            if ($error) {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t update line item: {message}', ['message' => $error]));
            } else {
                Craft::$app->getSession()->setError(Craft::t('commerce', 'Couldn’t update line item.'));
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
        $this->_cart = Plugin::getInstance()->getCarts()->getCart();

        $this->_cart->setFieldValuesFromRequest('fields');

        if (Plugin::getInstance()->getCarts()->removeFromCart($this->_cart, $lineItemId)) {
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
    public function actionRemoveAllLineItems(): Response
    {
        $this->requirePostRequest();

        $this->_cart = Plugin::getInstance()->getCarts()->getCart();

        $this->_cart->setFieldValuesFromRequest('fields');

        Plugin::getInstance()->getCarts()->clearCart($this->_cart);
        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $this->asJson(['success' => true, 'cart' => $this->cartArray($this->_cart)]);
        }
        Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Line items removed.'));
        $this->redirectToPostedUrl();
    }

    /**
     * Updates the cart with optional params.
     */
    public function actionUpdateCart()
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();

        $this->_cart = Plugin::getInstance()->getCarts()->getCart();

        // Saving current cart if it's new
        if (!$this->_cart->id && !Craft::$app->getElements()->saveElement($this->_cart, false)) {
            throw new Exception(Craft::t('commerce', 'Error creating new cart'));
        }

        $this->_cart->setFieldValuesFromRequest('fields');

        $cartSaved = false;

        $request = Craft::$app->getRequest();
        $sameAddress = $request->getParam('sameAddress');

        $updateErrors = [];

        if (null !== $request->getParam('purchasableId')) {
            $purchasableId = $request->getRequiredParam('purchasableId');
            $note = $request->getParam('note', '');
            $options = $request->getParam('options', []);
            $qty = $request->getParam('qty', 1);
            $error = '';
            if (!$plugin->getCarts()->addToCart($this->_cart, $purchasableId, $qty, $note, $options, $error)) {
                $addToCartError = Craft::t('commerce', 'Could not add to cart: {error}', [
                    'error' => $error,
                ]);
                $updateErrors['lineItems'] = $addToCartError;
            } else {
                $cartSaved = true;
            }
        }

        // Set Addresses
        if (null !== $request->getParam('shippingAddressId') && is_numeric($request->getParam('shippingAddressId'))) {
            $error = '';
            if ($shippingAddressId = $request->getParam('shippingAddressId')) {
                if ($shippingAddress = $plugin->getAddresses()->getAddressById($shippingAddressId)) {
                    if (!$sameAddress) {
                        if ($billingAddressId = $request->getParam('billingAddressId')) {
                            if ($billingAddress = $plugin->getAddresses()->getAddressById($billingAddressId)) {
                                if (!$this->_setOrderAddresses($shippingAddress, $billingAddress, $error)) {
                                    $updateErrors['addresses'] = $error;
                                } else {
                                    $cartSaved = true;
                                }
                            }
                        } else {
                            $billingAddress = new Address();
                            $billingAddress->setAttributes($request->getParam('billingAddress'));
                            $result = $this->_setOrderAddresses($shippingAddress, $billingAddress, $error);
                            if (!$result) {
                                if ($billingAddress->hasErrors()) {
                                    $updateErrors['billingAddress'] = Craft::t('commerce', 'Could not save the billing address.');
                                }
                            } else {
                                $cartSaved = true;
                            }
                        }
                    } else {
                        if (!$this->_setOrderAddresses($shippingAddress, $shippingAddress, $error)) {
                            $updateErrors['shippingAddress'] = Craft::t('commerce', 'Could not save the shipping address.');
                        } else {
                            $cartSaved = true;
                        }
                    }
                } else {
                    $updateErrors['shippingAddressId'] = Craft::t('commerce', 'No shipping address found with that ID.');
                }
            }
        } elseif (null !== $request->getParam('shippingAddress')) {
            $shippingAddress = new Address();
            $shippingAddress->setAttributes($request->getParam('shippingAddress'), false);
            if (!$sameAddress) {
                $billingAddressId = $request->getParam('billingAddressId');
                $billingAddress = null;

                if (is_numeric($billingAddressId)) {
                    $billingAddress = $plugin->getAddresses()->getAddressById((int)$billingAddressId);
                }

                if (!$billingAddress) {
                    $billingAddress = new Address();
                    $billingAddress->setAttributes($request->getParam('billingAddress'));
                }

                $result = $this->_setOrderAddresses($shippingAddress, $billingAddress, $error);
            } else {
                $result = $this->_setOrderAddresses($shippingAddress, $shippingAddress, $error);
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
            if (null !== $request->getParam('email')) {
                $error = '';
                $email = $request->getParam('email'); // empty string vs null (strict type checking)
                if (!$plugin->getCarts()->setEmail($this->_cart, $email, $error)) {
                    $updateErrors['email'] = $error;
                } else {
                    $cartSaved = true;
                }
            }
        }

        // Set guest email address onto guest customer and order.
        if (null !== $request->getParam('paymentCurrency')) {
            $currency = $request->getParam('paymentCurrency'); // empty string vs null (strict type checking)
            $error = '';
            if (!$plugin->getCarts()->setPaymentCurrency($this->_cart, $currency, $error)) {
                $updateErrors['paymentCurrency'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // Set Coupon on Cart.
        if (null !== $request->getParam('couponCode')) {
            $error = '';
            $couponCode = $request->getParam('couponCode');
            if (!$plugin->getCarts()->applyCoupon($this->_cart, $couponCode, $error)) {
                $updateErrors['couponCode'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // Set Gateway on Cart.
        if (null !== $request->getParam('gatewayId')) {
            $error = '';
            $gatewayId = $request->getParam('gatewayId');
            if (!$plugin->getCarts()->setGateway($this->_cart, $gatewayId, $error)) {
                $updateErrors['gatewayId'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // Set Payment source on Cart.
        if (null !== $request->getParam('paymentSourceId')) {
            $error = '';
            $paymentSourceId = $request->getParam('paymentSourceId');
            if (!$plugin->getCarts()->setPaymentSource($this->_cart, $paymentSourceId, $error)) {
                $updateErrors['$paymentSourceId'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // Set Shipping Method on Cart.
        if (null !== $request->getParam('shippingMethod')) {
            $error = '';
            $shippingMethod = $request->getParam('shippingMethod');
            if (!$plugin->getCarts()->setShippingMethod($this->_cart, $shippingMethod, $error)) {
                $updateErrors['shippingMethod'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // If they had fields in the post data, but nothing else made the cart save, save the custom fields manually.
        if (null !== $request->getParam('fields') && !$cartSaved) {
            Craft::$app->getElements()->saveElement($this->_cart);
        }

        // Clean up error array
        $updateErrors = array_filter($updateErrors);

        if (empty($updateErrors)) {
            Craft::$app->getSession()->setNotice(Craft::t('commerce', 'Cart updated.'));
            if ($request->getAcceptsJson()) {
                $this->asJson(['success' => true, 'cart' => $this->cartArray($this->_cart)]);
            }
            $this->redirectToPostedUrl();
        } else {
            $error = Craft::t('commerce', 'Cart not completely updated.');
            $this->_cart->addErrors($updateErrors);

            if ($request->getAcceptsJson()) {
                $this->asJson(['error' => $error, 'cart' => $this->cartArray($this->_cart)]);
            } else {
                Craft::$app->getSession()->setError($error);
            }
        }
    }

    // Private Methods
    // =========================================================================

    /**
     * @param Address $shippingAddress
     * @param Address $billingAddress
     * @param         $error
     * @return bool
     * @throws \Exception
     */
    private function _setOrderAddresses(Address $shippingAddress, Address $billingAddress, &$error): bool
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $customerId = $this->_cart->customerId;
            $currentCustomerAddressIds = Plugin::getInstance()->getCustomers()->getAddressIds($customerId);

            $ownAddress = true;
            // Customers can only set addresses that are theirs
            if ($shippingAddress->id && !in_array($shippingAddress->id, $currentCustomerAddressIds, false)) {
                $ownAddress = false;
            }
            // Customer can only set addresses that are theirs
            if ($billingAddress->id && !in_array($billingAddress->id, $currentCustomerAddressIds, false)) {
                $ownAddress = false;
            }

            // TODO shouldn't code terminate in this case, then, instead of continuing as normal
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
