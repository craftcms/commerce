<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\events\CartEvent;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use yii\base\Component;
use yii\base\Exception;
use yii\validators\EmailValidator;

/**
 * Cart service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.0
 */
class Cart extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event CartEvent The event that is raised before an item is added to cart
     */
    const EVENT_BEFORE_ADD_TO_CART = 'beforeAddToCart';

    /**
     * @event CartEvent The event that is raised after an item is added to cart
     */
    const EVENT_AFTER_ADD_TO_CART = 'afterAddToCart';

    /**
     * @event CartEvent The event that is raised before an item is removed from cart
     *
     * You may set [[CartEvent::isValid]] to `false` to prevent the item from being removed from the cart.
     */
    const EVENT_BEFORE_REMOVE_FROM_CART = 'beforeRemoveFromCart';

    /**
     * @event CartEvent The event that is raised after an item is removed from cart
     */
    const EVENT_AFTER_REMOVE_FROM_CART = 'afterRemoveFromCart';

    // Properties
    // =========================================================================

    /** @var string Session key for storing the cart number */
    protected $cookieCartId = 'commerce_cookie';

    /** @var Order */
    private $_cart;

    // Public Methods
    // =========================================================================

    /**
     * @param Order  $order
     * @param int    $purchasableId
     * @param int    $qty
     * @param string $note
     * @param array  $options
     * @param string $error
     *
     * @return bool
     * @throws \Exception
     */
    public function addToCart(Order $order, $purchasableId, $qty = 1, $note = '', $options = [], &$error = '')
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        $isNewLineItem = false;

        //saving current cart if it's new and empty
        if (!$order->id && !Plugin::getInstance()->getOrders()->saveOrder($order)) {
            Db::rollbackStackedTransaction();
            throw new Exception(Craft::t('commerce', 'Error on creating empty cart'));
        }

        //filling item model
        $lineItem = Plugin::getInstance()->getLineItems()->getLineItemByOrderPurchasableOptions($order->id, $purchasableId, $options);

        if ($lineItem) {
            foreach ($order->getLineItems() as $item) {
                if ($item->id == $lineItem->id) {
                    $lineItem = $item;
                }
            }
            $lineItem->qty += $qty;
        } else {
            $lineItem = Plugin::getInstance()->getLineItems()->createLineItem($purchasableId, $order, $options, $qty);
            $isNewLineItem = true;
        }

        if ($note) {
            $lineItem->note = $note;
        }

        $lineItem->validate();

        $lineItem->purchasable->validateLineItem($lineItem);

        try {
            if (!$lineItem->hasErrors()) {
                //raising event
                $event = new CartEvent([
                    'lineItem' => $lineItem,
                    'order' => $order
                ]);
                $this->trigger(self::EVENT_BEFORE_ADD_TO_CART, $event);

                if (Plugin::getInstance()->getLineItems()->saveLineItem($lineItem)) {
                    if ($isNewLineItem) {
                        $linesItems = $order->getLineItems();
                        $linesItems[] = $lineItem;
                        $order->setLineItems($linesItems);
                    }

                    Plugin::getInstance()->getOrders()->saveOrder($order);

                    $transaction->commit();

                    //raising event
                    $event = new CartEvent([
                        'lineItem' => $lineItem,
                        'order' => $order
                    ]);
                    $this->trigger(self::EVENT_AFTER_ADD_TO_CART, $event);

                    return true;
                }
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $transaction->rollBack();

        $errors = $lineItem->errors;
        $error = array_pop($errors);

        return false;
    }

    /**
     * @param Order  $cart
     * @param string $code
     * @param string $error
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function applyCoupon(Order $cart, $code, &$error = '')
    {
        if (empty($code) || Plugin::getInstance()->getDiscounts()->matchCode($code, $cart->customerId, $error)) {
            $cart->couponCode = $code ?: null;
            Plugin::getInstance()->getOrders()->saveOrder($cart);

            return true;
        }

        return false;
    }

    /**
     * Sets the payment currency on the order.
     *
     * @param $order
     * @param $currency
     * @param $error
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function setPaymentCurrency($order, $currency, $error)
    {
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($currency);

        if (!$currency) {
            $error = Craft::t("commerce", "Not an available payment currency");

            return false;
        }

        $order->paymentCurrency = $currency->iso;

        if (!Plugin::getInstance()->getOrders()->saveOrder($order)) {
            return false;
        };

        return true;
    }

    /**
     * Set shipping method to the current order
     *
     * @param Order  $cart
     * @param int    $shippingMethod
     * @param string $error ;
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function setShippingMethod(Order $cart, $shippingMethod, &$error = "")
    {
        $methods = Plugin::getInstance()->getShippingMethods()->getAvailableShippingMethods($cart);

        foreach ($methods as $method) {
            if ($method['handle'] == $shippingMethod) {
                $cart->shippingMethodHandle = $shippingMethod;

                return Plugin::getInstance()->getOrders()->saveOrder($cart);
            }
        }

        $error = Craft::t('commerce', 'Shipping method not available');

        return false;
    }

    /**
     * Set shipping method to the current order
     *
     * @param Order  $cart
     * @param int    $paymentMethodId
     * @param string $error
     *
     * @return bool
     * @throws \Exception
     */
    public function setPaymentMethod(Order $cart, $paymentMethodId, &$error = "")
    {
        $method = Plugin::getInstance()->getPaymentMethods()->getPaymentMethodById($paymentMethodId);

        if (!$method) {
            $error = Craft::t('commerce', 'Payment method does not exist or is not allowed.');

            return false;
        }

        $cart->paymentMethodId = $paymentMethodId;
        Plugin::getInstance()->getOrders()->saveOrder($cart);

        return true;
    }

    /**
     * @param Order               $cart
     * @param                     $email
     * @param string              $error
     *
     * @return bool
     */
    public function setEmail(Order $cart, $email, &$error = "")
    {

        $validator = new EmailValidator();

        if (empty($email) || !$validator->validate($email)) {
            $error = Craft::t('commerce', 'Not a valid email address');

            return false;
        }

        try {
            // we need to force a persisted customer so get a customer id
            $this->getCart()->customerId = Plugin::getInstance()->getCustomers()->getCustomerId();
            $customer = Plugin::getInstance()->getCustomers()->getCustomer();
            if (!$customer->userId) {
                $customer->email = $email;
                Plugin::getInstance()->getCustomers()->saveCustomer($customer);
                $cart->email = $customer->email;
                Plugin::getInstance()->getOrders()->saveOrder($cart);
            }
        } catch (Exception $e) {
            $error = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * @return mixed
     * @throws Exception
     * @throws \Exception
     */
    public function getCart()
    {

        if (null === $this->_cart) {
            $number = $this->_getSessionCartNumber();

            if ($this->_cart = Plugin::getInstance()->getOrders()->getOrderByNumber($number)) {
                // We do not want to use the same order number as a completed order.
                if ($this->_cart->isCompleted) {
                    $this->forgetCart();
                    Plugin::getInstance()->getCustomers()->forgetCustomer();
                    $this->getCart();
                }
            } else {
                $this->_cart = new Order();
                $this->_cart->number = $number;
            }

            $this->_cart->lastIp = Craft::$app->getRequest()->userIP;
            $this->_cart->orderLocale = Craft::$app->language;

            // Right now, orders are all stored in the default currency
            $this->_cart->currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

            // Payment currency is always set to the stores primary currency unless it is set to an allowed currency.
            $currencies = \array_column(Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies(), 'iso');

            if (defined('COMMERCE_PAYMENT_CURRENCY')) {
                $currency = StringHelper::toUpperCase(COMMERCE_PAYMENT_CURRENCY);
                if (in_array($currency, $currencies)) {
                    $this->_cart->paymentCurrency = $currency;
                }
            }

            $this->_cart->paymentCurrency = $this->_cart->paymentCurrency ?: Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

            // Update the cart if the customer has changed and recalculate the cart.
            $customer = Plugin::getInstance()->getCustomers()->getCustomer();
            if (!$this->_cart->isEmpty() && $this->_cart->customerId != $customer->id) {
                $this->_cart->customerId = $customer->id;
                $this->_cart->email = $customer->email;
                $this->_cart->billingAddressId = null;
                $this->_cart->shippingAddressId = null;
                Plugin::getInstance()->getOrders()->saveOrder($this->_cart);
            }
        }

        return $this->_cart;
    }

    /**
     * @return mixed|string
     */
    private function _getSessionCartNumber()
    {
        $session = Craft::$app->getSession();
        $cartNumber = $session[$this->cookieCartId];

        if (!$cartNumber) {
            $cartNumber = $session->set($this->cookieCartId, $this->_uniqueCartNumber());
        }

        return $cartNumber;
    }

    /**
     * @return string
     */
    private function _uniqueCartNumber(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * Forgets a Cart by deleting its cookie.
     */
    public function forgetCart()
    {
        $this->_cart = null;
        $session = Craft::$app->getSession();
        $session->remove($this->cookieCartId);
    }

    /**
     * Removes a line item from the cart.
     *
     * @param Order $cart
     * @param int   $lineItemId
     *
     *
     * @return bool
     */
    public function removeFromCart(Order $cart, $lineItemId)
    {
        /** @var LineItem $lineItem */
        $lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);

        // Fail if the line item does not belong to the cart.
        if (!$lineItem || ($cart->id != $lineItem->orderId)) {
            return false;
        }

        $event = new CartEvent([
            'lineItem' => $lineItem,
            'order' => $cart
        ]);
        $this->trigger(self::EVENT_BEFORE_REMOVE_FROM_CART, $event);

        if (!$event->isValid) {
            return false;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $lineItems = $cart->getLineItems();
            foreach ($lineItems as $key => $item) {
                if ($item->id == $lineItem->id) {
                    unset($lineItems[$key]);
                    $cart->setLineItems($lineItems);
                }
            }
            Plugin::getInstance()->getLineItems()->deleteLineItem($lineItem);
            Plugin::getInstance()->getOrders()->saveOrder($cart);

            //raising event
            $event = new CartEvent([
                'lineItem' => $lineItem,
                'order' => $cart
            ]);
            $this->trigger(self::EVENT_AFTER_REMOVE_FROM_CART, $event);
        } catch (\Exception $e) {
            Db::rollbackStackedTransaction();
            Craft::error($e->getMessage(), 'commerce');

            return false;
        }

        $transaction->commit();

        return true;
    }

    /**
     * Remove all items from a cart
     *
     * @param Order $cart
     *
     * @throws \Exception
     */
    public function clearCart(Order $cart)
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            Plugin::getInstance()->getLineItems()->deleteAllLineItemsByOrderId($cart->id);
            Plugin::getInstance()->getOrders()->saveOrder($cart);
        } catch (\Exception $e) {
            Db::rollbackStackedTransaction();
            throw $e;
        }

        $transaction->commit();
    }

    /**
     * Removes all carts that are incomplete and older than the config setting.
     *
     * @return int The number of carts purged from the database
     * @throws \Exception
     */
    public function purgeIncompleteCarts()
    {
        $doPurge = Plugin::getInstance()->getSettings()->purgeInactiveCarts;

        if ($doPurge) {
            $cartIds = $this->getCartsIdsToPurge();
            foreach ($cartIds as $id) {
                Craft::$app->getElements()->deleteElementById($id);
            }

            return count($cartIds);
        }

        return 0;
    }

    /**
     * Which Carts IDs need to be deleted
     *
     * @return int[]
     */
    private function getCartsIdsToPurge()
    {
        $configInterval = Plugin::getInstance()->getSettings()->purgeInactiveCartsDuration;
        $edge = new \DateTime();
        $interval = new \DateInterval($configInterval);
        $interval->invert = 1;
        $edge->add($interval);

        return (new Query())
            ->select(['orders.id'])
            ->where('isCompleted=:isCompleted AND dateUpdated <= :edge', [':isCompleted' => 'not 1', 'edge' => $edge->format('Y-m-d H:i:s')])
            ->from(['orders' => '{{%commerce_orders}}'])
            ->column();
    }
}
