<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\Plugin;
use craft\errors\ElementNotFoundException;
use craft\helpers\Html;
use LitEmoji\LitEmoji;
use Throwable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Class Cart Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class CartController extends BaseFrontEndController
{
    /**
     * @var Order The cart element
     */
    private $_cart;

    /**
     * @var string the name of the cart variable
     */
    private $_cartVariable;


    public function init()
    {
        $this->_cartVariable = Plugin::getInstance()->getSettings()->cartVariable;

        parent::init();
    }

    /**
     * Returns the cart as JSON
     */
    public function actionGetCart()
    {
        $this->requireAcceptsJson();

        $this->_cart = $this->_getCart();

        return $this->asJson([$this->_cartVariable => $this->cartArray($this->_cart)]);
    }

    /**
     * Updates the cart by adding purchasables to the cart, updating line items, or updating various cart attributes.
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function actionUpdateCart()
    {
        $this->requirePostRequest();
        $currentUser = Craft::$app->getUser()->getIdentity();
        $isSiteRequest = Craft::$app->getRequest()->getIsSiteRequest();
        /** @var Plugin $plugin */
        $plugin = Plugin::getInstance();

        // Get the cart from the request or from the session.
        $this->_cart = $this->_getCart(true);

        // Services we will be using.
        $request = Craft::$app->getRequest();

        // Set the custom fields submitted
        $this->_cart->setFieldValuesFromRequest('fields');

        // Backwards compatible way of adding to the cart
        if ($purchasableId = $request->getParam('purchasableId')) {
            $note = LitEmoji::unicodeToShortcode($request->getParam('note', ''));
            $options = $request->getParam('options') ?: [];
            $qty = (int)$request->getParam('qty', 1);

            if ($qty > 0) {
                $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($this->_cart->id, $purchasableId, $options);

                // New line items already have a qty of one.
                if ($lineItem->id) {
                    $lineItem->qty += $qty;
                } else {
                    $lineItem->qty = $qty;
                }

                $lineItem->note = $note;

                $this->_cart->addLineItem($lineItem);
            }
        }

        // Add multiple items to the cart
        if ($purchasables = $request->getParam('purchasables')) {
            // Initially combine same purchasables
            $purchasablesByKey = [];
            foreach ($purchasables as $key => $purchasable) {
                $purchasableId = $request->getParam("purchasables.{$key}.id");
                $note = LitEmoji::unicodeToShortcode($request->getParam("purchasables.{$key}.note", ''));
                $options = $request->getParam("purchasables.{$key}.options") ?: [];
                $qty = (int)$request->getParam("purchasables.{$key}.qty", 1);

                $purchasable = [];
                $purchasable['id'] = $purchasableId;
                $purchasable['options'] = $options;
                $purchasable['note'] = $note;
                $purchasable['qty'] = $qty;

                $key = $purchasableId . '-' . LineItemHelper::generateOptionsSignature($options);
                if (isset($purchasablesByKey[$key])) {
                    $purchasablesByKey[$key]['qty'] += $purchasable['qty'];
                } else {
                    $purchasablesByKey[$key] = $purchasable;
                }
            }

            foreach ($purchasablesByKey as $purchasable) {
                if ($purchasable['id'] == null) {
                    continue;
                }

                // Ignore zero value qty for multi-add forms https://github.com/craftcms/commerce/issues/330#issuecomment-384533139
                if ($purchasable['qty'] > 0) {
                    $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($this->_cart->id, $purchasable['id'], $purchasable['options']);

                    // New line items already have a qty of one.
                    if ($lineItem->id) {
                        $lineItem->qty += $purchasable['qty'];
                    } else {
                        $lineItem->qty = $purchasable['qty'];
                    }

                    $lineItem->note = $purchasable['note'];
                    $this->_cart->addLineItem($lineItem);
                }
            }
        }

        // Update multiple line items in the cart
        if ($lineItems = $request->getParam('lineItems')) {
            foreach ($lineItems as $key => $lineItem) {
                $lineItemId = $key;
                $note = LitEmoji::unicodeToShortcode($request->getParam("lineItems.{$key}.note"));
                $options = $request->getParam("lineItems.{$key}.options");
                $qty = $request->getParam("lineItems.{$key}.qty");
                $removeLine = $request->getParam("lineItems.{$key}.remove");

                $lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);

                // Line item not found, or does not belong to their order
                if (!$lineItem || ($this->_cart->id != $lineItem->orderId)) {
                    throw new NotFoundHttpException('Line item not found');
                }

                if ($qty) {
                    $lineItem->qty = $qty;
                }

                if ($note) {
                    $lineItem->note = $note;
                }

                if ($options) {
                    $lineItem->setOptions($options);
                }

                if ($qty !== null && $qty == 0) {
                    $removeLine = true;
                }

                if ($removeLine) {
                    $this->_cart->removeLineItem($lineItem);
                } else {
                    $this->_cart->addLineItem($lineItem);
                }
            }
        }

        $this->_setAddresses();

        // Set guest email address onto guest customers order.
        if (!$this->_cart->getUser() && $email = $request->getParam('email')) {
            $this->_cart->setEmail($email);
        }

        // Set if the customer should be registered on order completion
        if ($registerUserOnOrderComplete = $request->getBodyParam('registerUserOnOrderComplete')) {
            $this->_cart->registerUserOnOrderComplete = true;
        }

        // Set payment currency on cart
        if ($currency = $request->getParam('paymentCurrency')) {
            $this->_cart->paymentCurrency = $currency;
        }

        // Set Coupon on Cart. Allow blank string to remove coupon
        if (($couponCode = $request->getParam('couponCode')) !== null) {
            $this->_cart->couponCode = trim($couponCode) ?: null;
        }

        // Set Payment Gateway on cart
        if ($gatewayId = $request->getParam('gatewayId')) {
            if ($gateway = $plugin->getGateways()->getGatewayById($gatewayId)) {
                $this->_cart->setGatewayId($gatewayId);
            }
        }

        // Submit payment source on cart
        if ($paymentSourceId = $request->getParam('paymentSourceId')) {
            if ($paymentSource = $plugin->getPaymentSources()->getPaymentSourceById($paymentSourceId)) {
                // The payment source can only be used by the same user as the cart's user.
                $cartUserId = $this->_cart->getUser() ? $this->_cart->getUser()->id : null;
                $paymentSourceUserId = $paymentSource->getUser() ? $paymentSource->getUser()->id : null;
                $allowedToUsePaymentSource = ($cartUserId && $paymentSourceUserId && $currentUser && $isSiteRequest && ($paymentSourceUserId == $cartUserId));
                if ($allowedToUsePaymentSource) {
                    $this->_cart->setPaymentSource($paymentSource);
                }
            }
        }

        // Set Shipping method on cart.
        if ($shippingMethodHandle = $request->getParam('shippingMethodHandle')) {
            $this->_cart->shippingMethodHandle = $shippingMethodHandle;
        }

        return $this->_returnCart();
    }


    /**
     * @return Response
     * @throws Exception
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws BadRequestHttpException
     */
    private function _returnCart()
    {
        $request = Craft::$app->getRequest();

        if (!$this->_cart->validate() || !Craft::$app->getElements()->saveElement($this->_cart, false)) {
            $error = Plugin::t('Unable to update cart.');

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'error' => $error,
                    'errors' => $this->_cart->getErrors(),
                    'success' => !$this->_cart->hasErrors(),
                    $this->_cartVariable => $this->cartArray($this->_cart)
                ]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                $this->_cartVariable => $this->_cart
            ]);

            Craft::$app->getSession()->setError($error);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => !$this->_cart->hasErrors(),
                $this->_cartVariable => $this->cartArray($this->_cart)
            ]);
        }

        if (($cartUpdatedNotice = $request->getParam('cartUpdatedNotice')) !== null) {
            Craft::$app->getSession()->setNotice(Html::encode($cartUpdatedNotice));
        } else {
            Craft::$app->getSession()->setNotice(Plugin::t('Cart updated.'));
        }

        Craft::$app->getUrlManager()->setRouteParams([
            $this->_cartVariable => $this->_cart
        ]);

        return $this->redirectToPostedUrl();
    }

    /**
     * @param bool $forceSave Force the cart to save to the DB
     * @return Order|null
     *
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws NotFoundHttpException
     * @throws Throwable
     */
    private function _getCart($forceSave = false)
    {
        $request = Craft::$app->getRequest();

        $cart = null;

        if ($orderNumber = $request->getBodyParam('orderNumber')) {
            // Get the cart from the order number
            $cart = Order::find()->number($orderNumber)->isCompleted(false)->one();

            if (!$cart) {
                throw new NotFoundHttpException('Cart not found');
            }

            return $cart;
        }

        $requestForceSave = (bool)$request->getBodyParam('forceSave');
        $doForceSave = ($requestForceSave || $forceSave);

        return Plugin::getInstance()->getCarts()->getCart($doForceSave);
    }

    /**
     * Set addresses on the cart.
     */
    private function _setAddresses()
    {
        // Address updating
        $request = Craft::$app->getRequest();

        $shippingIsBilling = $request->getParam('shippingAddressSameAsBilling');
        $billingIsShipping = $request->getParam('billingAddressSameAsShipping');
        $estimatedBillingIsShipping = $request->getParam('estimatedBillingAddressSameAsShipping');
        $shippingAddress = $request->getParam('shippingAddress');
        $estimatedShippingAddress = $request->getParam('estimatedShippingAddress');
        $billingAddress = $request->getParam('billingAddress');
        $estimatedBillingAddress = $request->getParam('estimatedBillingAddress');

        // Override billing address with a particular ID
        $shippingAddressId = $request->getParam('shippingAddressId');
        $billingAddressId = $request->getParam('billingAddressId');

        // Shipping address
        if ($shippingAddressId && !$shippingIsBilling) {
            $address = Plugin::getInstance()->getAddresses()->getAddressByIdAndCustomerId($shippingAddressId, $this->_cart->customerId);

            $this->_cart->setShippingAddress($address);
        } else if ($shippingAddress && !$shippingIsBilling) {
            $this->_cart->setShippingAddress($shippingAddress);
        }

        // Billing address
        if ($billingAddressId && !$billingIsShipping) {
            $address = Plugin::getInstance()->getAddresses()->getAddressByIdAndCustomerId($billingAddressId, $this->_cart->customerId);

            $this->_cart->setBillingAddress($address);
        } else if ($billingAddress && !$billingIsShipping) {
            $this->_cart->setBillingAddress($billingAddress);
        }

        // Estimated Shipping Address
        if ($estimatedShippingAddress) {
            if ($this->_cart->estimatedShippingAddressId) {
                $address = Plugin::getInstance()->getAddresses()->getAddressById($this->_cart->estimatedShippingAddressId);
                $address->setAttributes($estimatedShippingAddress, false);
                $estimatedShippingAddress = $address;
            }

            $this->_cart->setEstimatedShippingAddress($estimatedShippingAddress);
        }

        // Estimated Billing Address
        if ($estimatedBillingAddress && !$estimatedBillingIsShipping) {
            if ($this->_cart->estimatedBillingAddressId && ($this->_cart->estimatedBillingAddressId != $this->_cart->estimatedShippingAddressId)) {
                $address = Plugin::getInstance()->getAddresses()->getAddressById($this->_cart->estimatedBillingAddressId);
                $address->setAttributes($estimatedBillingAddress, false);
                $estimatedBillingAddress = $address;
            }

            $this->_cart->setEstimatedBillingAddress($estimatedBillingAddress);
        }

        $this->_cart->billingSameAsShipping = (bool)$billingIsShipping;
        $this->_cart->shippingSameAsBilling = (bool)$shippingIsBilling;
        $this->_cart->estimatedBillingSameAsShipping = (bool)$estimatedBillingIsShipping;

        // Set primary addresses
        if ($request->getBodyParam('makePrimaryShippingAddress')) {
            $this->_cart->makePrimaryShippingAddress = true;
        }

        if ($request->getBodyParam('makePrimaryBillingAddress')) {
            $this->_cart->makePrimaryBillingAddress = true;
        }

        // Shipping
        if ($shippingAddressId && !$shippingIsBilling && $billingIsShipping) {
            $this->_cart->billingAddressId = $shippingAddressId;
        }

        // Billing
        if ($billingAddressId && !$billingIsShipping && $shippingIsBilling) {
            $this->_cart->shippingAddressId = $billingAddressId;
        }
    }
}
