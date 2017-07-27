<?php
namespace Craft;


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
class Commerce_CartService extends BaseApplicationComponent
{
    /** @var string Session key for storing the cart number */
    protected $cookieCartId = 'commerce_cookie';

    /** @var Commerce_OrderModel */
    private $_cart;

    /**
     * @param Commerce_OrderModel $order
     * @param int                 $purchasableId
     * @param int                 $qty
     * @param string              $note
     * @param array               $options
     * @param string              $error
     *
     * @return bool
     * @throws \Exception
     */
    public function addToCart($order, $purchasableId, $qty = 1, $note = '', $options = [], &$error = '')
    {

        $isNewLineItem = false;

        //saving current cart if it's new and empty
        if (!$order->id && !craft()->commerce_orders->saveOrder($order))
        {
            $error = Craft::t('Could not save the cart.');

            return false;
        }

        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;

        //filling item model
        $lineItem = craft()->commerce_lineItems->getLineItemByOrderPurchasableOptions($order->id, $purchasableId, $options);

        if ($lineItem)
        {
            foreach ($order->getLineItems() as $item)
            {
                if ($item->id == $lineItem->id)
                {
                    $lineItem = $item;
                }
            }
            $lineItem->qty += $qty;
        }
        else
        {
            $lineItem = craft()->commerce_lineItems->createLineItem($purchasableId, $order, $options, $qty);
            $isNewLineItem = true;
        }

        if ($note)
        {
            $lineItem->note = $note;
        }

        $lineItem->validate();

        $lineItem->purchasable->validateLineItem($lineItem);

        try
        {
            if (!$lineItem->hasErrors())
            {
                //raising event
                $event = new Event($this, ['lineItem' => $lineItem, 'order' => $order,]);
                $this->onBeforeAddToCart($event);

                if (!$event->performAction)
                {
                    if ($transaction !== null)
                    {
                        $transaction->rollback();
                    }

                    return false;
                }

                if (craft()->commerce_lineItems->saveLineItem($lineItem))
                {
                    if ($isNewLineItem)
                    {
                        $linesItems = $order->getLineItems();
                        $linesItems[] = $lineItem;
                        $order->setLineItems($linesItems);
                    }

                    craft()->commerce_orders->saveOrder($order);

                    if ($transaction !== null)
                    {
                        $transaction->commit();
                    }

                    //raising event
                    $event = new Event($this, ['lineItem' => $lineItem, 'order' => $order,]);
                    $this->onAddToCart($event);

                    return true;
                }
            }
        }
        catch (\Exception $e)
        {
            if ($transaction !== null)
            {
                $transaction->rollback();
            }
            throw $e;
        }

        if ($transaction !== null)
        {
            $transaction->rollback();
        }

        $errors = $lineItem->getAllErrors();
        $error = array_pop($errors);

        return false;
    }

    /**
     * Before Event
     * Event params: order(Commerce_OrderModel), lineItem (Commerce_LineItemModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeAddToCart(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel))
        {
            throw new Exception('onAddToCart event requires "order" param with OrderModel instance');
        }

        if (empty($params['lineItem']) || !($params['lineItem'] instanceof Commerce_LineItemModel))
        {
            throw new Exception('onAddToCart event requires "lineItem" param with LineItemModel instance');
        }
        $this->raiseEvent('onBeforeAddToCart', $event);
    }

    /**
     * Event method.
     * Event params: order(Commerce_OrderModel), lineItem (Commerce_LineItemModel)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onAddToCart(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel))
        {
            throw new Exception('onAddToCart event requires "order" param with OrderModel instance');
        }

        if (empty($params['lineItem']) || !($params['lineItem'] instanceof Commerce_LineItemModel))
        {
            throw new Exception('onAddToCart event requires "lineItem" param with LineItemModel instance');
        }
        $this->raiseEvent('onAddToCart', $event);
    }

    /**
     * Forgets a Cart by deleting its cookie.
     */
    public function forgetCart()
    {
        $this->_cart = null;
        $cookieId = $this->cookieCartId;
        craft()->userSession->deleteStateCookie($cookieId);
    }

    /**
     * @param Commerce_OrderModel $cart
     * @param string              $code
     * @param string              $error
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function applyCoupon(Commerce_OrderModel $cart, $code, &$error = '')
    {
        if (empty($code) || craft()->commerce_discounts->matchCode($code, $cart->customerId, $error))
        {
            $cart->couponCode = $code ?: null;
            craft()->commerce_orders->saveOrder($cart);

            return true;
        }
        else
        {
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
    public function setPaymentCurrency($order, $currency, &$error = '')
    {
        $currency = craft()->commerce_paymentCurrencies->getPaymentCurrencyByIso($currency);

        if (!$currency)
        {
            $error = Craft::t("Not an available payment currency");

            return false;
        }

        $order->paymentCurrency = $currency->iso;

        if (!craft()->commerce_orders->saveOrder($order))
        {
            return false;
        };

        return true;
    }

    /**
     * Set shipping method to the current order
     *
     * @param Commerce_OrderModel $cart
     * @param int                 $shippingMethod
     * @param string              $error ;
     *
     * @return bool
     * @throws Exception
     * @throws \Exception
     */
    public function setShippingMethod(Commerce_OrderModel $cart, $shippingMethod, &$error = '')
    {
        $methods = craft()->commerce_shippingMethods->getAvailableShippingMethods($cart);

        foreach ($methods as $method)
        {
            if ($method['handle'] == $shippingMethod)
            {
                $cart->shippingMethod = $shippingMethod;

                return craft()->commerce_orders->saveOrder($cart);
            }
        }

        $error = Craft::t('Shipping method not available');

        return false;
    }

    /**
     * Set shipping method to the current order
     *
     * @param Commerce_OrderModel $cart
     * @param int                 $paymentMethodId
     * @param string              $error
     *
     * @return bool
     * @throws \Exception
     */
    public function setPaymentMethod(Commerce_OrderModel $cart, $paymentMethodId, &$error = "")
    {
        $method = craft()->commerce_paymentMethods->getPaymentMethodById($paymentMethodId);

        if (!$method || !$method->frontendEnabled)
        {
            $error = Craft::t('Payment method does not exist or is not allowed.');

            return false;
        }

        $cart->paymentMethodId = $paymentMethodId;
        craft()->commerce_orders->saveOrder($cart);

        return true;
    }

    /**
     * @param Commerce_OrderModel $cart
     * @param                     $email
     * @param string              $error
     *
     * @return bool
     */
    public function setEmail(Commerce_OrderModel $cart, $email, &$error = "")
    {

        $validator = new \CEmailValidator;
        $validator->allowEmpty = false;

        if (!$validator->validateValue($email))
        {
            $error = Craft::t('Not a valid email address');

            return false;
        }

        try
        {
            // we need to force a persisted customer so get a customer id
            $this->getCart()->customerId = craft()->commerce_customers->getCustomerId();
            $customer = craft()->commerce_customers->getCustomer();
            if (!$customer->userId)
            {
                $customer->email = $email;
                craft()->commerce_customers->saveCustomer($customer);
                $cart->email = $customer->email;
                craft()->commerce_orders->saveOrder($cart);
            }
        }
        catch (Exception $e)
        {
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

        if (!isset($this->_cart))
        {
            $number = $this->_getSessionCartNumber();

            if ($this->_cart = craft()->commerce_orders->getOrderByNumber($number))
            {
                // We do not want to use the same order number as a completed order.
                if ($this->_cart->isCompleted)
                {
                    $this->forgetCart();
                    craft()->commerce_customers->forgetCustomer();
                    $this->getCart();
                }
            }
            else
            {
                $this->_cart = new Commerce_OrderModel;
                $this->_cart->number = $number;
            }

            $this->_cart->lastIp = craft()->request->getIpAddress();
            $this->_cart->orderLocale = craft()->language;

            // Right now, orders are all stored in the default currency
            $this->_cart->currency = craft()->commerce_paymentCurrencies->getPrimaryPaymentCurrencyIso();

            // Payment currency is always set to the stores primary currency unless it is set to an allowed currency.
            $allCurrencies = craft()->commerce_paymentCurrencies->getAllPaymentCurrencies();
            $currencies = [];

            foreach ($allCurrencies as $currency)
            {
                $currencies[] = $currency->iso;
            }

            if (defined('COMMERCE_PAYMENT_CURRENCY'))
            {
                $currency = StringHelper::toUpperCase(COMMERCE_PAYMENT_CURRENCY);
                if (in_array($currency, $currencies))
                {
                    $this->_cart->paymentCurrency = $currency;
                }
            }

            // If payment currency is not set or not available anymore, default to the primary currency
            if (!in_array($this->_cart->paymentCurrency, $currencies))
            {
                $this->_cart->paymentCurrency = craft()->commerce_paymentCurrencies->getPrimaryPaymentCurrencyIso();
            }

            if (
                $autoSetAddresses = craft()->config->get('autoSetNewCartAddresses', 'commerce') &&
                $customer = craft()->commerce_customers->getCustomerById($this->_cart->customerId)
            )
            {
                if (
                    !$this->_cart->shippingAddressId &&
                    ($lastShippingAddressId = $customer->lastUsedShippingAddressId) &&
                    ($address = craft()->commerce_addresses->getAddressById($lastShippingAddressId))
                )
                {
                    $this->_cart->shippingAddressId = $address->id;
                }

                if (
                    !$this->_cart->billingAddressId &&
                    ($lastBillingAddressId = $customer->lastUsedBillingAddressId) &&
                    ($address = craft()->commerce_addresses->getAddressById($lastBillingAddressId))
                )
                {
                    $this->_cart->billingAddressId = $address->id;
                }
            }

            // Update the cart if the customer has changed and recalculate the cart.
            $customer = craft()->commerce_customers->getCustomer();
            if (!$this->_cart->isEmpty() && $this->_cart->customerId != $customer->id)
            {
                $this->_cart->customerId = $customer->id;
                $this->_cart->email = $customer->email;
                $this->_cart->billingAddressId = null;
                $this->_cart->shippingAddressId = null;
                craft()->commerce_orders->saveOrder($this->_cart);
            }
        }

        return $this->_cart;
    }

    /**
     * @return mixed|string
     */
    private function _getSessionCartNumber()
    {
        $cookieId = $this->cookieCartId;
        $cartNumber = craft()->userSession->getStateCookieValue($cookieId);

        if (!$cartNumber)
        {
            $cartNumber = md5(uniqid(mt_rand(), true));
            $configInterval = craft()->config->get('cartCookieDuration', 'commerce');
            $interval = new DateInterval($configInterval);
            $cartExpiry = date_create('@0')->add($interval)->getTimestamp();
            craft()->userSession->saveCookie($cookieId, $cartNumber, $cartExpiry);
        }

        return $cartNumber;
    }

    /**
     * Removes a line item from the cart.
     *
     * @param Commerce_OrderModel $cart
     * @param int                 $lineItemId
     *
     * @throws Exception
     * @throws \Exception
     *
     * @return bool
     */
    public function removeFromCart(Commerce_OrderModel $cart, $lineItemId)
    {
        $lineItem = craft()->commerce_lineItems->getLineItemById($lineItemId);

        // Fail if the line item does not belong to the cart.
        if (!$lineItem || ($cart->id != $lineItem->orderId))
        {
            return false;
        }

        //raising event
        $event = new Event($this, ['lineItem' => $lineItem, 'order' => $cart]);
        $this->onBeforeRemoveFromCart($event);

        if (!$event->performAction)
        {
            return false;
        }
        else
        {
            $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
            try
            {
                $lineItems = $cart->getLineItems();
                foreach ($lineItems as $key => $item)
                {
                    if ($item->id == $lineItem->id)
                    {
                        unset($lineItems[$key]);
                        $cart->setLineItems($lineItems);
                    }
                }
                craft()->commerce_lineItems->deleteLineItem($lineItem);
                craft()->commerce_orders->saveOrder($cart);

                //raising event
                $event = new Event($this, ['lineItemId' => $lineItemId, 'order' => $cart]);
                $this->onRemoveFromCart($event);
            }
            catch (\Exception $e)
            {
                if ($transaction !== null)
                {
                    $transaction->rollback();
                }
                CommercePlugin::log($e->getMessage(), LogLevel::Error, true);

                return false;
            }

            if ($transaction !== null)
            {
                $transaction->commit();
            }
        }

        return true;
    }

    /**
     * Event method.
     * Event params: order(Commerce_OrderModel), lineItemId (int)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onBeforeRemoveFromCart(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel))
        {
            throw new Exception('onBeforeRemoveFromCart event requires "order" param with OrderModel instance');
        }

        if (empty($params['lineItem']) || !($params['lineItem'] instanceof Commerce_LineItemModel))
        {
            throw new Exception('onBeforeRemoveFromCart event requires "lineItem" param to be an Commerce_LineItemModel');
        }
        $this->raiseEvent('onBeforeRemoveFromCart', $event);
    }

    /**
     * Event method.
     * Event params: order(Commerce_OrderModel), lineItemId (int)
     *
     * @param \CEvent $event
     *
     * @throws \CException
     */
    public function onRemoveFromCart(\CEvent $event)
    {
        $params = $event->params;
        if (empty($params['order']) || !($params['order'] instanceof Commerce_OrderModel))
        {
            throw new Exception('onRemoveFromCart event requires "order" param with OrderModel instance');
        }

        if (empty($params['lineItemId']) || !is_numeric($params['lineItemId']))
        {
            throw new Exception('onRemoveFromCart event requires "lineItemId" param');
        }
        $this->raiseEvent('onRemoveFromCart', $event);
    }

    /**
     * Remove all items from a cart
     *
     * @param Commerce_OrderModel $cart
     *
     * @throws \Exception
     */
    public function clearCart(Commerce_OrderModel $cart)
    {
        $transaction = craft()->db->getCurrentTransaction() === null ? craft()->db->beginTransaction() : null;
        try
        {
            craft()->commerce_lineItems->deleteAllLineItemsByOrderId($cart->id);
            craft()->commerce_orders->saveOrder($cart);
        }
        catch (\Exception $e)
        {
            if ($transaction !== null)
            {
                $transaction->rollback();
            }
            throw $e;
        }

        if ($transaction !== null)
        {
            $transaction->commit();
        }
    }

    /**
     * Removes all carts that are incomplete and older than the config setting.
     *
     * @return int The number of carts purged from the database
     * @throws \Exception
     */
    public function purgeIncompleteCarts()
    {
        $doPurge = craft()->config->get('purgeInactiveCarts', 'commerce');
        $cartIds = $this->getCartsIdsToPurge();

        if ($cartIds && $doPurge)
        {
            craft()->elements->deleteElementById($cartIds);
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
        $configInterval = craft()->config->get('purgeInactiveCartsDuration', 'commerce');
        $edge = new \DateTime();
        $interval = new \DateInterval($configInterval);
        $interval->invert = 1;
        $edge->add($interval);

        return Craft::app()->db->createCommand()->select('orders.id')
            ->from('commerce_orders orders')
            ->where('isCompleted=:isCompleted AND dateUpdated <= :edge', [':isCompleted' => 'not 1','edge' => $edge->format('Y-m-d H:i:s')])
            ->queryColumn();
    }

    /**
     * Returns a DbCommand object prepped for retrieving order records.
     *
     * @return DbCommand
     */
    private function _createOrderQuery()
    {
        return craft()->db->createCommand()->select('orders.id,
                    orders.number,
                    orders.orderStatusId,
                    orders.billingAddressId,
                    orders.shippingAddressId,
                    orders.customerId,
                    orders.couponCode,
                    orders.itemTotal,
                    orders.baseDiscount,
                    orders.baseTax,
                    orders.baseTaxIncluded,
                    orders.baseShippingCost,
                    orders.totalPrice,
                    orders.totalPaid,
                    orders.email,
                    orders.dateOrdered,
                    orders.datePaid,
                    orders.currency,
                    orders.lastIp,
                    orders.message,
                    orders.returnUrl,
                    orders.cancelUrl,
                    orders.shippingMethod,
                    orders.paymentMethodId')->from('commerce_orders orders');
    }
}
