<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Order;
use craft\commerce\helpers\LineItem as LineItemHelper;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\elements\User;
use craft\errors\ElementNotFoundException;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
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
    protected $_cart;

    /**
     * @var string the name of the cart variable
     */
    protected $_cartVariable;

    /**
     * @var User|null
     */
    protected $_currentUser;

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        $this->_cartVariable = Plugin::getInstance()->getSettings()->cartVariable;
        $this->_currentUser = Craft::$app->getUser()->getIdentity();

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
     * @throws InvalidConfigException
     */
    public function actionUpdateCart()
    {
        $this->requirePostRequest();
        $isSiteRequest = Craft::$app->getRequest()->getIsSiteRequest();
        /** @var Plugin $plugin */
        $plugin = Plugin::getInstance();

        // Get the cart from the request or from the session.
        // When we are about to update the cart, we consider it a real cart at this point, and want to actually create it in the DB.
        $this->_cart = $this->_getCart(true);

        // Can clear notices when updating the cart
        if (($clearNotices = $this->request->getParam('clearNotices')) !== null) {
            $this->_cart->clearNotices();
        }

        // Set the custom fields submitted
        $this->_cart->setFieldValuesFromRequest('fields');

        // Backwards compatible way of adding to the cart
        if ($purchasableId = $this->request->getParam('purchasableId')) {
            $note = $this->request->getParam('note', '');
            $options = $this->request->getParam('options', []); // TODO Commerce 4 should only support key value only #COM-55
            $qty = (int)$this->request->getParam('qty', 1);

            if ($qty > 0) {
                $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($this->_cart, $purchasableId, $options);

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
        if ($purchasables = $this->request->getParam('purchasables')) {
            // Initially combine same purchasables
            $purchasablesByKey = [];
            foreach ($purchasables as $key => $purchasable) {
                $purchasableId = $this->request->getParam("purchasables.{$key}.id");
                $note = $this->request->getParam("purchasables.{$key}.note", '');
                $options = $this->request->getParam("purchasables.{$key}.options", []);
                $qty = (int)$this->request->getParam("purchasables.{$key}.qty", 1);

                $purchasable = [];
                $purchasable['id'] = $purchasableId;
                $purchasable['options'] = $options;
                $purchasable['note'] = $note;
                $purchasable['qty'] = (int) $qty;

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
                    $lineItem = Plugin::getInstance()->getLineItems()->resolveLineItem($this->_cart, $purchasable['id'], $purchasable['options']);

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
        if ($lineItems = $this->request->getParam('lineItems')) {
            foreach ($lineItems as $key => $lineItem) {
                $lineItem = $this->_getCartLineItemById($key);
                if ($lineItem) {
                    $lineItem->qty = (int) $this->request->getParam("lineItems.{$key}.qty", $lineItem->qty);
                    $lineItem->note = $note = $this->request->getParam("lineItems.{$key}.note", $lineItem->note);
                    $lineItem->setOptions($this->request->getParam("lineItems.{$key}.options", $lineItem->getOptions()));

                    $removeLine = $this->request->getParam("lineItems.{$key}.remove", false);
                    if (($lineItem->qty !== null && $lineItem->qty == 0) || $removeLine) {
                        $this->_cart->removeLineItem($lineItem);
                    } else {
                        $this->_cart->addLineItem($lineItem);
                    }
                }
            }
        }

        $this->_setAddresses();

        // Set guest email address onto guest customers order.
        if (!$this->_cart->getUser() && $email = $this->request->getParam('email')) {
            $this->_cart->setEmail($email);
        }

        // Set if the customer should be registered on order completion
        if ($this->request->getBodyParam('registerUserOnOrderComplete')) {
            $this->_cart->registerUserOnOrderComplete = true;
        }

        if ($this->request->getBodyParam('registerUserOnOrderComplete') === 'false') {
            $this->_cart->registerUserOnOrderComplete = false;
        }

        // Set payment currency on cart
        if ($currency = $this->request->getParam('paymentCurrency')) {
            $this->_cart->paymentCurrency = $currency;
        }

        // Set Coupon on Cart. Allow blank string to remove coupon
        if (($couponCode = $this->request->getParam('couponCode')) !== null) {
            $this->_cart->couponCode = trim($couponCode) ?: null;
        }

        // Set Payment Gateway on cart
        if ($gatewayId = $this->request->getParam('gatewayId')) {
            if ($gateway = $plugin->getGateways()->getGatewayById($gatewayId)) {
                $this->_cart->setGatewayId($gatewayId);
            }
        }

        // Submit payment source on cart
        if (($paymentSourceId = $this->request->getParam('paymentSourceId')) !== null) {
            if ($paymentSourceId && $paymentSource = $plugin->getPaymentSources()->getPaymentSourceById($paymentSourceId)) {
                // The payment source can only be used by the same user as the cart's user.
                $cartUserId = $this->_cart->getUser() ? $this->_cart->getUser()->id : null;
                $paymentSourceUserId = $paymentSource->getUser() ? $paymentSource->getUser()->id : null;
                $allowedToUsePaymentSource = ($cartUserId && $paymentSourceUserId && $this->_currentUser && $isSiteRequest && ($paymentSourceUserId == $cartUserId));
                if ($allowedToUsePaymentSource) {
                    $this->_cart->setPaymentSource($paymentSource);
                }
            } else {
                $this->_cart->setPaymentSource(null);
            }
        }

        // Set Shipping method on cart.
        if ($shippingMethodHandle = $this->request->getParam('shippingMethodHandle')) {
            $this->_cart->shippingMethodHandle = $shippingMethodHandle;
        }

        return $this->_returnCart();
    }

    /**
     * @return Response|null
     * @since 3.1
     */
    public function actionLoadCart()
    {
        $number = $this->request->getParam('number');
        $redirect = Plugin::getInstance()->getSettings()->loadCartRedirectUrl ?: UrlHelper::siteUrl();

        if (!$number) {
            $error = Craft::t('commerce', 'A cart number must be specified.');

            if ($this->request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $this->setFailFlash($error);
            return $this->request->getIsGet() ? $this->redirect($redirect) : null;
        }

        $cart = Order::find()->number($number)->isCompleted(false)->one();

        if (!$cart) {
            $error = Craft::t('commerce', 'Unable to retrieve cart.');

            if ($this->request->getAcceptsJson()) {
                return $this->asErrorJson($error);
            }

            $this->setFailFlash($error);
            return $this->request->getIsGet() ? $this->redirect($redirect) : null;
        }

        $cartCustomer = $cart->getCustomer();

        $session = Craft::$app->getSession();
        $carts = Plugin::getInstance()->getCarts();
        $carts->forgetCart();
        $session->set($carts->getCartName(), $number);

        if ($this->request->getAcceptsJson()) {
            return $this->asJson(['success' => true]);
        }

        return $this->request->getIsGet() ? $this->redirect($redirect) : $this->redirectToPostedUrl();
    }

    /**
     * @return Response
     * @since 3.3
     */
    public function actionComplete()
    {
        /** @var Plugin $plugin */
        $plugin = Plugin::getInstance();
        $this->requirePostRequest();

        if (!$plugin->getSettings()->allowCheckoutWithoutPayment) {
            throw new HttpException(401, Craft::t('commerce', 'You must make a payment to complete the order.'));
        }

        $this->_cart = $this->_getCart();
        $errors = [];

        // Check email address exists on order.
        if (empty($this->_cart->email)) {
            $errors['email'] = Craft::t('commerce', 'No customer email address exists on this cart.');
        }

        if ($plugin->getSettings()->allowEmptyCartOnCheckout && $this->_cart->getIsEmpty()) {
            $errors['lineItems'] = Craft::t('commerce', 'Order can not be empty.');
        }

        if ($plugin->getSettings()->requireShippingMethodSelectionAtCheckout && !$this->_cart->getShippingMethod()) {
            $errors['shippingMethodHandle'] = Craft::t('commerce', 'There is no shipping method selected for this order.');
        }

        if ($plugin->getSettings()->requireBillingAddressAtCheckout && !$this->_cart->billingAddressId) {
            $errors['billingAddressId'] = Craft::t('commerce', 'Billing address required.');
        }

        if ($plugin->getSettings()->requireShippingAddressAtCheckout && !$this->_cart->shippingAddressId) {
            $errors['shippingAddressId'] = Craft::t('commerce', 'Shipping address required.');
        }

        // Set if the customer should be registered on order completion
        if ($this->request->getBodyParam('registerUserOnOrderComplete')) {
            $this->_cart->registerUserOnOrderComplete = true;
        }

        if ($this->request->getBodyParam('registerUserOnOrderComplete') === 'false') {
            $this->_cart->registerUserOnOrderComplete = false;
        }

        if (!empty($errors)) {
            $this->_cart->addErrors($errors);
        }


        if (empty($errors)) {

            $completedSuccess = false;

            try {
                $completedSuccess = $this->_cart->markAsComplete();
            } catch (\Exception $exception) {
                $completedSuccess = false;
            }

            if (!$completedSuccess) {
                $this->_cart->addError('isComplete', Craft::t('commerce', 'Completing order failed.'));
            }
        }

        return $this->_returnCart();
    }

    /**
     * @param $lineItemId |nulls
     * @return LineItem|null
     */
    private function _getCartLineItemById($lineItemId)
    {
        $lineItem = null;

        foreach ($this->_cart->getLineItems() as $item) {
            if ($item->id && $item->id == $lineItemId) {
                $lineItem = $item;
            }
        }

        return $lineItem;
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
        // Allow validation of custom fields when passing this param
        $validateCustomFields = Plugin::getInstance()->getSettings()->validateCartCustomFieldsOnSubmission;

        // Do we want to validate fields submitted
        $customFieldAttributes = [];

        if ($validateCustomFields) {
            // $fields will be null so
            if ($submittedFields = $this->request->getBodyParam('fields')) {
                $this->_cart->setScenario(Element::SCENARIO_LIVE);
                $customFieldAttributes = array_keys($submittedFields);
            }
        }

        $attributes = array_merge($this->_cart->activeAttributes(), $customFieldAttributes);

        $updateCartSearchIndexes = Plugin::getInstance()->getSettings()->updateCartSearchIndexes;

        // Do not clear errors, as errors could be added to the cart before _returnCart is called.
        if (!$this->_cart->validate($attributes, false) || !Craft::$app->getElements()->saveElement($this->_cart, false, false, $updateCartSearchIndexes)) {
            $error = Craft::t('commerce', 'Unable to update cart.');
            $message = $this->request->getValidatedBodyParam('failMessage') ?? $error;

            if ($this->request->getAcceptsJson()) {
                return $this->asJson([
                    'error' => $error,
                    'errors' => $this->_cart->getErrors(),
                    'success' => !$this->_cart->hasErrors(),
                    'message' => $message,
                    $this->_cartVariable => $this->cartArray($this->_cart)
                ]);
            }

            Craft::$app->getUrlManager()->setRouteParams([
                $this->_cartVariable => $this->_cart
            ]);

            $this->setFailFlash($error);

            return null;
        }

        $cartUpdatedMessage = Craft::t('commerce', 'Cart updated.');

        if ($this->request->getAcceptsJson()) {
            $message = $this->request->getValidatedBodyParam('successMessage') ?? $cartUpdatedMessage;

            return $this->asJson([
                'success' => !$this->_cart->hasErrors(),
                $this->_cartVariable => $this->cartArray($this->_cart),
                'message' => $message
            ]);
        }

        $this->setSuccessFlash($cartUpdatedMessage);

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
        $cart = null;

        // TODO Remove `orderNumber` param in 4.0 #COM-33
        $orderNumber = $this->request->getBodyParam('orderNumber');
        $orderNumber = $this->request->getBodyParam('number', $orderNumber);

        if ($orderNumber) {
            // Get the cart from the order number
            $cart = Order::find()->number($orderNumber)->isCompleted(false)->one();

            if (!$cart) {
                throw new NotFoundHttpException('Cart not found');
            }

            return $cart;
        }

        $requestForceSave = (bool)$this->request->getBodyParam('forceSave');
        $doForceSave = ($requestForceSave || $forceSave);

        return Plugin::getInstance()->getCarts()->getCart($doForceSave);
    }

    /**
     * Set addresses on the cart.
     */
    private function _setAddresses()
    {
        // Address updating

        $shippingIsBilling = $this->request->getParam('shippingAddressSameAsBilling');
        $billingIsShipping = $this->request->getParam('billingAddressSameAsShipping');
        $estimatedBillingIsShipping = $this->request->getParam('estimatedBillingAddressSameAsShipping');
        $shippingAddress = $this->request->getParam('shippingAddress');
        $estimatedShippingAddress = $this->request->getParam('estimatedShippingAddress');
        $billingAddress = $this->request->getParam('billingAddress');
        $estimatedBillingAddress = $this->request->getParam('estimatedBillingAddress');

        // Override billing address with a particular ID
        $shippingAddressId = $this->request->getParam('shippingAddressId');
        $billingAddressId = $this->request->getParam('billingAddressId');

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
        if ($this->request->getBodyParam('makePrimaryShippingAddress')) {
            $this->_cart->makePrimaryShippingAddress = true;
        }

        if ($this->request->getBodyParam('makePrimaryBillingAddress')) {
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
