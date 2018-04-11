<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use craft\helpers\Json;
use yii\base\Exception;
use yii\base\InvalidArgumentException;
use yii\web\HttpException;
use yii\web\NotFoundHttpException;

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
     * @var Order The cart element
     */
    private $_cart;

    /**
     * @var string the name of the cart variable
     */
    private $_cartVariable;

    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->requirePostRequest();

        $this->_cartVariable = Plugin::getInstance()->getSettings()->cartVariable;
        // Get the cart from the request or from the session.
        $this->_cart = $this->_getCart();

        parent::init();
    }

    /**
     * Update quantity
     *
     * @throws Exception
     * @throws HttpException
     */
    public function actionUpdateLineItem()
    {
        $request = Craft::$app->getRequest();

        $lineItemId = $request->getParam('lineItemId');

        $lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);

        // Line item not found, or does not belong to their order
        if (!$lineItem || ($this->_cart->id != $lineItem->orderId)) {
            throw new NotFoundHttpException('Line item not found');
        }

        if ($qty = $request->getParam('qty')) {
            $lineItem->qty = $qty;
        }

        if ($note = $request->getParam('note')) {
            $lineItem->note = $note;
        }

        if ($options = $request->getParam('options')) {
            $lineItem->setOptions($options);
        }

        $this->_cart->addLineItem($lineItem);

        return $this->_returnCart();
    }

    /**
     * Remove Line item from the cart
     *
     * @throws Exception
     * @throws NotFoundHttpException
     */
    public function actionRemoveLineItem()
    {
        $request = Craft::$app->getRequest();

        $lineItemId = $request->getParam('lineItemId');

        $lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);

        // Line item not found, or does not belong to their order
        if (!$lineItem || ($this->_cart->id != $lineItem->orderId)) {
            throw new NotFoundHttpException('Line item not found');
        }

        $this->_cart->removeLineItem($lineItem);

        return $this->_returnCart();
    }

    /**
     * Remove all line items from the cart
     */
    public function actionRemoveAllLineItems()
    {
        $this->_cart->setLineItems([]);
    }

    /**
     * Updates the cart by adding purchasables to the cart, or various cart attributes.
     */
    public function actionUpdateCart()
    {
        // Services we will be using.
        $request = Craft::$app->getRequest();

        // Set the custom fields submitted
        $this->_cart->setFieldValuesFromRequest('fields');

        // Backwards compatible way of adding to the cart
        if ($purchasableId = $request->getParam('purchasableId')) {
            $note = $request->getParam('note', '');
            $options = $request->getParam('options') ?: [];
            $qty = (int)$request->getParam('qty', 1);

            $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($this->_cart->id, $purchasableId, $options, $qty, $note);
            $this->_cart->addLineItem($lineItem);
        }

        // Add multiple items to the cart
        if ($purchasables = $request->getParam('purchasables')) {
            foreach ($purchasables as $key => $purchasable) {
                $purchasableId = $request->getRequiredParam("purchasables.{$key}.id");
                $note = $request->getParam("purchasables.{$key}.note", '');
                $options = $request->getParam("purchasables.{$key}.options") ?: [];
                $qty = (int)$request->getParam("purchasables.{$key}.qty", 1);

                // Only add items that have a valid qty, avoids adding blank lines
                if ($qty > 0) {
                  $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($this->_cart->id, $purchasableId, $options, $qty, $note);
                  $this->_cart->addLineItem($lineItem);
                }
            }
        };

        $this->_setAddresses();


        // Set guest email address onto guest customer and order.
        if (Craft::$app->getUser()->isGuest && $email = $request->getParam('email')) {
            $this->_cart->setEmail($email);
        }

        // Set guest email address onto guest customer and order.
        if ($currency = $request->getParam('paymentCurrency')) {
            $this->_cart->paymentCurrency = $currency;
        }

        // Set Coupon on Cart. Allow blank string to remove coupon
        if (($couponCode = $request->getParam('couponCode')) !== null) {
            $this->_cart->couponCode = $couponCode ?: null;
        }

        // Set Coupon on Cart.
        if ($gatewayId = $request->getParam('gatewayId')) {
            $this->_cart->gatewayId = $gatewayId;
        }

        // Set Coupon on Cart.
        if ($paymentSourceId = $request->getParam('paymentSourceId')) {
            $this->_cart->paymentSourceId = $paymentSourceId;
        }

        // Set Coupon on Cart.
        if ($shippingMethodHandle = $request->getParam('shippingMethodHandle')) {
            $this->_cart->shippingMethodHandle = $shippingMethodHandle;
        }

        return $this->_returnCart();
    }

    // Private Methods
    // =========================================================================

    /**
     * @return \yii\web\Response
     * @throws Exception
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\web\BadRequestHttpException
     */
    private function _returnCart()
    {
        $request = Craft::$app->getRequest();

        if (!$this->_cart->validate() || !Craft::$app->getElements()->saveElement($this->_cart, false)) {

            $error = Craft::t('commerce', 'Unable to update cart.');

            if ($request->getAcceptsJson()) {
                return $this->asJson(['error' => $error, $this->_cartVariable => $this->cartArray($this->_cart)]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                $this->_cartVariable => $this->_cart
            ]);

            Craft::$app->getSession()->setError($error);

            return null;
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([$this->_cartVariable => $this->cartArray($this->_cart)]);
        }

        Craft::$app->getUrlManager()->setRouteParams([
            $this->_cartVariable => $this->_cart
        ]);

        return $this->redirectToPostedUrl();
    }

    /**
     * @return Order|null
     *
     * @throws NotFoundHttpException
     */
    private function _getCart()
    {
        $request = Craft::$app->getRequest();

        if ($orderNumber = $request->getBodyParam('orderNumber')) {
            // Get the cart from the order number
            $cart = Order::find()->number($orderNumber)->isCompleted(false)->one();
        } else {
            // Get the cart from the current users session, or return a new cart attached to the session
            $cart = Plugin::getInstance()->getCarts()->getCart();
        }

        if (!$cart) {
            throw new NotFoundHttpException('Cart not found');
        }

        return clone $cart;
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
        $shippingAddress = $request->getParam('shippingAddress');
        $billingAddress = $request->getParam('billingAddress');

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

        $this->_cart->billingSameAsShipping = $billingIsShipping;
        $this->_cart->shippingSameAsBilling = $shippingIsBilling;

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
