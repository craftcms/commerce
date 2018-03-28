<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\EmailException;
use craft\commerce\errors\GatewayException;
use craft\commerce\errors\LineItemException;
use craft\commerce\errors\PaymentSourceException;
use craft\commerce\errors\ShippingMethodException;
use craft\commerce\models\Address;
use craft\commerce\Plugin;
use craft\web\Response;
use yii\base\Exception;
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

    // Public Methods
    // =========================================================================

    public function init()
    {
        $this->requirePostRequest();

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
            ksort($options);
            $lineItem->options = $options;
            $lineItem->optionsSignature = md5(json_encode($options));
        }

        $this->_cart->addLineItem($lineItem);
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

        // Old way of adding to the cart
        if ($purchasableId = $request->getParam('purchasableId')) {
            $note = $request->getParam('note', '');
            $options = $request->getParam('options') ?: [];
            $qty = (int)$request->getParam('qty', 1);

            $this->_cart->addPurchasableToCart($purchasableId, $options, $qty, $note);
        }

        // Add multiple items to the cart
        if ($purchasables = $request->getParam('purchasables')) {
            foreach ($purchasables as $key => $purchasable) {
                $purchasableId = $request->getRequiredParam("purchasables.{$key}.id");
                $note = $request->getParam("purchasables.{$key}.note", '');
                $options = $request->getParam("purchasables.{$key}.options") ?: [];
                $qty = (int)$request->getParam("purchasables.{$key}.qty", 1);

                $this->_cart->addPurchasableToCart($purchasableId, $options, $qty, $note);
            }
        };

        if ($shippingAddress = $request->getParam('shippingAddress')) {
            $this->_cart->setShippingAddress($shippingAddress);
        }

        if ($billingAddress = $request->getParam('billingAddress')) {
            $this->_cart->setBillingAddress($billingAddress);
        }

        // Set guest email address onto guest customer and order.
        if (Craft::$app->getUser()->isGuest && $email = $request->getParam('email')) {
            $this->_cart->setEmail($email);
        }

        // Set guest email address onto guest customer and order.
        if ($currency = $request->getParam('paymentCurrency')) {
            $this->_cart->paymentCurrency = $currency;
        }

        // Set Coupon on Cart.
        if ($couponCode = $request->getParam('couponCode')) {
            $this->_cart->couponCode = $couponCode;
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
    }

    /**
     * Returns the cart
     */
    public function afterAction($action, $result)
    {
        $this->_returnCart();

        return parent::afterAction($action, $result);
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

        if (!$this->_cart->validate()) {

            $error = Craft::t('commerce', 'Unable to update cart.');

            if ($request->getAcceptsJson()) {
                $this->asJson(['error' => $error, 'cart' => $this->cartArray($this->_cart)]);
            } else {
                Craft::$app->getUrlManager()->setRouteParams([
                    'cart' => $this->_cart
                ]);

                Craft::$app->getSession()->setError($error);
            }
        }

        // Already validated.
        Craft::$app->getElements()->saveElement($this->_cart, false);

        if ($request->getAcceptsJson()) {
            $this->asJson(['cart' => $this->cartArray($this->_cart)]);
        } else {
            Craft::$app->getUrlManager()->setRouteParams([
                'cart' => $this->_cart
            ]);
        }

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

        if ($orderNumber = $request->getParam('orderNumber')) {
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
}
