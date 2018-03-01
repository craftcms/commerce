<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\EmailException;
use craft\commerce\errors\GatewayException;
use craft\commerce\errors\PaymentSourceException;
use craft\commerce\errors\ShippingMethodException;
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

        $cartsService = $plugin->getCarts();

        if (null !== $request->getParam('purchasableId')) {
            $purchasableId = $request->getRequiredParam('purchasableId');
            $note = $request->getParam('note', '');
            $options = $request->getParam('options', []);
            $qty = (int) $request->getParam('qty', 1);
            $error = '';

            $lineItem = $plugin->getLineItems()->resolveLineItem($this->_cart, $purchasableId, $options, $qty, $note);

            if (!$cartsService->addToCart($this->_cart, $lineItem)) {
                $addToCartError = Craft::t('commerce', 'Could not add to cart: {error}', [
                    'error' => $lineItem->hasErrors() ? array_values($lineItem->getFirstErrors())[0] : Craft::t('commerce', 'Server error')
                ]);

                $updateErrors['lineItems'] = $addToCartError;
            } else {
                $cartSaved = true;
            }

        }

        // Set Addresses
        $addressesService = $plugin->getAddresses();
        if (null !== $request->getParam('shippingAddressId') && is_numeric($request->getParam('shippingAddressId'))) {
            $error = '';
            if ($shippingAddressId = $request->getParam('shippingAddressId')) {
                if ($shippingAddress = $addressesService->getAddressById($shippingAddressId)) {
                    if (!$sameAddress) {
                        if ($billingAddressId = $request->getParam('billingAddressId')) {
                            if ($billingAddress = $addressesService->getAddressById($billingAddressId)) {
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
                    $billingAddress = $addressesService->getAddressById((int)$billingAddressId);
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
                $email = $request->getParam('email'); // empty string vs null (strict type checking)
                try {
                    $cartSaved = $cartsService->setEmail($this->_cart, $email);
                } catch (EmailException $exception) {
                    $updateErrors['email'] = $exception->getMessage();
                }
            }
        }

        // Set guest email address onto guest customer and order.
        if (null !== $request->getParam('paymentCurrency')) {
            $currency = $request->getParam('paymentCurrency'); // empty string vs null (strict type checking)
            try {
                $cartSaved = $cartsService->setPaymentCurrency($this->_cart, $currency);
            } catch (CurrencyException $exception) {
                $updateErrors['paymentCurrency'] = $exception->getMessage();
            }
        }

        // Set Coupon on Cart.
        if (null !== $request->getParam('couponCode')) {
            $error = '';
            $couponCode = $request->getParam('couponCode');
            if (!$cartsService->applyCoupon($this->_cart, $couponCode, $error)) {
                $updateErrors['couponCode'] = $error;
            } else {
                $cartSaved = true;
            }
        }

        // Set Gateway on Cart.
        if (null !== $request->getParam('gatewayId')) {
            $gatewayId = $request->getParam('gatewayId');
            try {
                $cartSaved = $cartsService->setGateway($this->_cart, (int)$gatewayId);
            } catch (GatewayException $exception) {
                $updateErrors['gatewayId'] = $exception->getMessage();
            }
        }

        // Set Payment source on Cart.
        if (null !== $request->getParam('paymentSourceId')) {
            $paymentSourceId = $request->getParam('paymentSourceId');
            try {
                $cartSaved = $cartsService->setPaymentSource($this->_cart, (int) $paymentSourceId);
            } catch (PaymentSourceException $exception) {
                $updateErrors['gatewayId'] = $exception->getMessage();
            }
        }

        // Set Shipping Method on Cart.
        if (null !== $request->getParam('shippingMethod')) {
            $shippingMethod = $request->getParam('shippingMethod');
            try {
                $cartSaved = $cartsService->setShippingMethod($this->_cart, $shippingMethod);
            } catch (ShippingMethodException $exception) {
                $updateErrors['shippingMethod'] = $exception->getMessage();
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

            if (!$ownAddress) {
                $error = Craft::t('commerce', 'Can not choose an address ID that does not belong to the customer.');
                return false;
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
