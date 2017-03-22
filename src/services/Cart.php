<?php
namespace craft\commerce\services;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\helpers\Db;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\StringHelper;
use yii\base\Component;
use yii\base\Event;
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
    /** @var string Session key for storing the cart number */
    protected $cookieCartId = 'commerce_cookie';

    /** @var Order */
    private $_cart;

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
        Db::beginStackedTransaction();

        $isNewLineItem = false;

        //saving current cart if it's new and empty
        if (!$order->id) {
            if (!Plugin::getInstance()->getOrders()->saveOrder($order)) {
                Db::rollbackStackedTransaction();
                throw new Exception(Craft::t('commerce', 'commerce', 'Error on creating empty cart'));
            }
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
                $event = new Event($this, ['lineItem' => $lineItem, 'order' => $order,]);
                $this->onBeforeAddToCart($event);

                if (!$event->performAction) {
                    Db::rollbackStackedTransaction();

                    return false;
                }

                if (Plugin::getInstance()->getLineItems()->saveLineItem($lineItem)) {
                    if ($isNewLineItem) {
                        $linesItems = $order->getLineItems();
                        $linesItems[] = $lineItem;
                        $order->setLineItems($linesItems);
                    }

                    Plugin::getInstance()->getOrders()->saveOrder($order);

                    Db::commitStackedTransaction();

                    //raising event
                    $event = new Event($this, ['lineItem' => $lineItem, 'order' => $order,]);
                    $this->onAddToCart($event);

                    return true;
                }
            }
        } catch (\Exception $e) {
            Db::rollbackStackedTransaction();
            throw $e;
        }

        Db::rollbackStackedTransaction();

        $errors = $lineItem->getAllErrors();
        $error = array_pop($errors);

        return false;
    }

    public function onBeforeAddToCart(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Order)) {
            throw new Exception('onAddToCart event requires "order" param with OrderModel instance');
        }

        if (empty($params['lineItem']) || !($params['lineItem'] instanceof LineItem)) {
            throw new Exception('onAddToCart event requires "lineItem" param with LineItemModel instance');
        }
        $this->raiseEvent('onBeforeAddToCart', $event);
    }

    public function onAddToCart(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Order)) {
            throw new Exception('onAddToCart event requires "order" param with OrderModel instance');
        }

        if (empty($params['lineItem']) || !($params['lineItem'] instanceof LineItem)) {
            throw new Exception('onAddToCart event requires "lineItem" param with LineItemModel instance');
        }
        $this->raiseEvent('onAddToCart', $event);
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
        } else {
            return false;
        }
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
                $cart->shippingMethod = $shippingMethod;

                return Plugin::getInstance()->getOrders()->saveOrder($cart);
            }
        }

        $error = Craft::t('commerce', 'commerce', 'Shipping method not available');

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
            $error = Craft::t('commerce', 'commerce', 'Payment method does not exist or is not allowed.');

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
            $error = Craft::t('commerce', 'commerce', 'Not a valid email address');

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

        if (!isset($this->_cart)) {
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

            $this->_cart->lastIp = Craft::$app->getRequest()->getIpAddress();
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
        $cartNumber = md5(uniqid(mt_rand(), true));

        return $cartNumber;
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

        //raising event
        $event = new Event($this, ['lineItem' => $lineItem, 'order' => $cart]);
        $this->onBeforeRemoveFromCart($event);

        if (!$event->performAction) {
            return false;
        } else {
            Db::beginStackedTransaction();
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
                $event = new Event($this, ['lineItemId' => $lineItemId, 'order' => $cart]);
                $this->onRemoveFromCart($event);
            } catch (\Exception $e) {
                Db::rollbackStackedTransaction();
                Craft::error($e->getMessage(), 'commerce');

                return false;
            }

            Db::commitStackedTransaction();
        }

        return true;
    }


    public function onBeforeRemoveFromCart(\CEvent $event)
    {
        // TODO: Refactor for Craft 3
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Order)) {
            throw new Exception('onBeforeRemoveFromCart event requires "order" param with OrderModel instance');
        }

        if (empty($params['lineItem']) || !($params['lineItem'] instanceof LineItem)) {
            throw new Exception('onBeforeRemoveFromCart event requires "lineItem" param to be an LineItem');
        }
        $this->raiseEvent('onBeforeRemoveFromCart', $event);
    }

    public function onRemoveFromCart(\CEvent $event)
    {
        // TODO: Refactor for Craft 3
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Order)) {
            throw new Exception('onRemoveFromCart event requires "order" param with OrderModel instance');
        }

        if (empty($params['lineItemId']) || !is_numeric($params['lineItemId'])) {
            throw new Exception('onRemoveFromCart event requires "lineItemId" param');
        }
        $this->raiseEvent('onRemoveFromCart', $event);
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
        Db::beginStackedTransaction();
        try {
            Plugin::getInstance()->getLineItems()->deleteAllLineItemsByOrderId($cart->id);
            Plugin::getInstance()->getOrders()->saveOrder($cart);
        } catch (\Exception $e) {
            Db::rollbackStackedTransaction();
            throw $e;
        }

        Db::commitStackedTransaction();
    }

    /**
     * Removes all carts that are incomplete and older than the config setting.
     *
     * @return int The number of carts purged from the database
     * @throws \Exception
     */
    public function purgeIncompleteCarts()
    {
        $doPurge = Craft::$app->getConfig()->get('purgeInactiveCarts', 'commerce');
        $cartIds = $this->getCartsIdsToPurge();

        if ($cartIds && $doPurge) {
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
        $configInterval = Craft::$app->getConfig()->get('purgeInactiveCartsDuration', 'commerce');
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
