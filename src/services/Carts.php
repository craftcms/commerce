<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\services;

use Craft;
use craft\commerce\base\Gateway;
use craft\commerce\elements\Order;
use craft\commerce\errors\CurrencyException;
use craft\commerce\errors\ShippingMethodException;
use craft\commerce\events\CartEvent;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\db\Query;
use craft\helpers\StringHelper;
use yii\base\Component;
use yii\base\Exception;
use yii\validators\EmailValidator;

/**
 * Cart service.
 *
 * @property Order $cart
 * @property Order $email
 * @property Order $gateway the shipping method to the current order
 * @property mixed $paymentCurrency the payment currency on the order
 * @property Order $shippingMethod the shipping method to the current order
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Carts extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event CartEvent The event that is raised before an item is added to cart
     *
     * Plugins can get notified before an item is added to the cart.
     *
     * ```php
     * use craft\commerce\events\CartEvent;
     * use craft\commerce\services\Cart;
     * use yii\base\Event;
     *
     * Event::on(Cart::class, Cart::EVENT_BEFORE_ADD_TO_CART, function(CartEvent $e) {
     *      // Perhaps perform some extra steps based on the item being added to the cart.
     * });
     * ```
     */
    const EVENT_BEFORE_ADD_TO_CART = 'beforeAddToCart';

    /**
     * @event CartEvent The event that is raised after an item is added to cart
     *
     * Plugins can get notified after an item has been added to the cart.
     *
     * ```php
     * use craft\commerce\events\CartEvent;
     * use craft\commerce\services\Cart;
     * use yii\base\Event;
     *
     * Event::on(Cart::class, Cart::EVENT_BEFORE_ADD_TO_CART, function(CartEvent $e) {
     *      // Maybe let the warehouse system to flag one unit of the product as "reserved"
     * });
     * ```
     */
    const EVENT_AFTER_ADD_TO_CART = 'afterAddToCart';

    /**
     * @event CartEvent The event that is raised before an item is removed from cart
     * You may set [[CartEvent::isValid]] to `false` to prevent the item from being removed from the cart.
     *
     * Plugins can get notified before an item is removed from the cart.
     *
     * ```php
     * use craft\commerce\events\CartEvent;
     * use craft\commerce\services\Cart;
     * use yii\base\Event;
     *
     * Event::on(Cart::class, Cart::EVENT_BEFORE_ADD_TO_CART, function(CartEvent $e) {
     *      // Maybe prevent this item from being removed, if this item is required for some other item.
     * });
     * ```
     */
    const EVENT_BEFORE_REMOVE_FROM_CART = 'beforeRemoveFromCart';

    /**
     * @event CartEvent The event that is raised after an item is removed from cart
     *
     * Plugins can get notified after an item has been removed from the cart.
     *
     * ```php
     * use craft\commerce\events\CartEvent;
     * use craft\commerce\services\Cart;
     * use yii\base\Event;
     *
     * Event::on(Cart::class, Cart::EVENT_BEFORE_ADD_TO_CART, function(CartEvent $e) {
     *      // Perhaps, if this item was a dependency for some other item, remove that one too.
     * });
     * ```
     */
    const EVENT_AFTER_REMOVE_FROM_CART = 'afterRemoveFromCart';

    // Properties
    // =========================================================================

    /**
     * @var string Session key for storing the cart number
     */
    protected $cartName = 'commerce_cookie';

    /**
     * @var Order
     */
    private $_cart;

    // Public Methods
    // =========================================================================

    /**
     * Add a line item to a cart.
     *
     * @param Order $order the cart
     * @param LineItem $lineItem
     * @return bool whether item was added to the cart
     * @throws Exception if unable to create a cart
     */
    public function addToCart(Order $order, LineItem $lineItem): bool
    {
        // saving current cart if it's new and empty
        if (!$order->id && !Craft::$app->getElements()->saveElement($order)) {
            throw new Exception(Craft::t('commerce', 'Error on creating empty cart'));
        }

        // filling item model
        $plugin = Plugin::getInstance();

        if (!$lineItem->validate()) {
            return false;
        }

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            if (!$lineItem->hasErrors()) {
                // Raise the 'beforeAddToCart' event
                if ($this->hasEventHandlers(self::EVENT_BEFORE_ADD_TO_CART)) {
                    $this->trigger(self::EVENT_BEFORE_ADD_TO_CART, new CartEvent([
                        'lineItem' => $lineItem,
                        'order' => $order
                    ]));
                }

                $isNewLineItem = !$lineItem->id;

                if (!$plugin->getLineItems()->saveLineItem($lineItem)) {
                    return false;
                }

                if ($isNewLineItem) {
                    $order->addLineItem($lineItem);
                }

                Craft::$app->getElements()->saveElement($order);
                $transaction->commit();
            }
        } catch (\Throwable $e) {
            $transaction->rollBack();

            throw $e;
        }

        // Raise the 'afterAddToCart' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_ADD_TO_CART)) {
            $this->trigger(self::EVENT_AFTER_ADD_TO_CART, new CartEvent([
                'lineItem' => $lineItem,
                'order' => $order
            ]));
        }

        return true;
    }

    /**
     * Apply a coupon by its code to a cart.
     *
     * @param Order $cart the cart
     * @param string $code the coupon code
     * @param string $explanation error message (if any) will be set on this by reference
     * @return bool whether the coupon was applied successfully
     */
    public function applyCoupon(Order $cart, $code, &$explanation): bool
    {
        if (empty($code) || Plugin::getInstance()->getDiscounts()->matchCode($code, $cart->customerId, $explanation)) {
            $cart->couponCode = $code ?: null;

            return Craft::$app->getElements()->saveElement($cart);
        }

        return false;
    }

    /**
     * Sets the payment currency on the order.
     *
     * @param Order $order the order
     * @param string $currency the ISO code for currency
     * @return bool whether the currency was set successfully
     * @throws CurrencyException if currency not found
     */
    public function setPaymentCurrency($order, $currency): bool
    {
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($currency);
        $order->paymentCurrency = $currency->iso;

        return Craft::$app->getElements()->saveElement($order);
    }

    /**
     * Sets shipping method to the current order.
     *
     * @param Order $cart
     * @param string $shippingMethodHandle
     * @return bool whether the method was set successfully
     * @throws ShippingMethodException if shipping method not found
     */
    public function setShippingMethod(Order $cart, string $shippingMethodHandle): bool
    {
        $methods = Plugin::getInstance()->getShippingMethods()->getAvailableShippingMethods($cart);

        foreach ($methods as $method) {
            if ($method['handle'] == $shippingMethodHandle) {
                $cart->shippingMethodHandle = $shippingMethodHandle;

                return Craft::$app->getElements()->saveElement($cart);
            }
        }

        throw new ShippingMethodException(Craft::t('commerce', 'Shipping method “{handle}” not available', ['handle' => $shippingMethodHandle]));
    }

    /**
     * Sets gateway to the current cart
     *
     * @param Order $cart the cart
     * @param int $gatewayId the gateway id
     * @param string $error error message (if any) will be set on this by reference
     * @return bool
     */
    public function setGateway(Order $cart, int $gatewayId, &$error): bool
    {
        if (!$gatewayId) {
            $error = Craft::t('commerce', 'Payment gateway does not exist or is not allowed.');

            return false;
        }

        /** @var Gateway $gateway */
        $gateway = Plugin::getInstance()->getGateways()->getGatewayById($gatewayId);

        if (!$gateway || (Craft::$app->getRequest()->getIsSiteRequest() && !$gateway->frontendEnabled)) {
            $error = Craft::t('commerce', 'Payment gateway does not exist or is not allowed.');

            return false;
        }

        $cart->gatewayId = $gatewayId;
        Craft::$app->getElements()->saveElement($cart);

        return true;
    }

    /**
     * Set a payment source on the cart
     *
     * @param Order $cart the cart
     * @param int $paymentSourceId ID of payment source
     * @param string $error error message (if any) will be set on this by reference
     * @return bool whether the source was set successfully
     */
    public function setPaymentSource(Order $cart, int $paymentSourceId, &$error): bool
    {
        $user = Craft::$app->getUser();

        if ($user->getIsGuest()) {
            $error = Craft::t('commerce', 'You must be logged in to select a payment source.');
        }

        $source = Plugin::getInstance()->getPaymentSources()->getPaymentSourceById($paymentSourceId);

        if (!$source) {
            $error = Craft::t('commerce', 'Payment source does not exist or is not allowed.');
        }

        // TODO maybe allow admins to do this?
        if ($user->getId() !== $source->userId) {
            $error = Craft::t('commerce', 'Payment source does not exist or is not allowed.');
        }

        $cart->gatewayId = null;
        $cart->paymentSourceId = $paymentSourceId;
        Craft::$app->getElements()->saveElement($cart);

        return true;
    }

    /**
     * Set an email address on the cart.
     *
     * @param Order $cart the cart
     * @param string $email the email address to set
     * @param string $error error message (if any) will be set on this by reference
     * @return bool whether the email address was set successfully
     */
    public function setEmail(Order $cart, $email, &$error): bool
    {
        $validator = new EmailValidator();

        if (empty($email) || !$validator->validate($email)) {
            $error = Craft::t('commerce', 'Not a valid email address');

            return false;
        }

        if ($cart->getCustomer() && $cart->getCustomer()->getUser()) {
            $error = Craft::t('commerce', 'Can not set email on a cart as a logged in and registered user.');

            return false;
        }

        try {
            $cart->setEmail($email);
            Craft::$app->getElements()->saveElement($cart);
        } catch (Exception $e) {
            $error = $e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * Get the current cart for this session.
     *
     * @return Order
     */
    public function getCart(): Order
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
            $allCurrencies = Plugin::getInstance()->getPaymentCurrencies()->getAllPaymentCurrencies();
            $currencies = [];

            foreach ($allCurrencies as $currency) {
                $currencies[] = $currency->iso;
            }

            if (defined('COMMERCE_PAYMENT_CURRENCY')) {
                $currency = StringHelper::toUpperCase(COMMERCE_PAYMENT_CURRENCY);
                if (in_array($currency, $currencies, false)) {
                    $this->_cart->paymentCurrency = $currency;
                }
            }

            $this->_cart->paymentCurrency = $this->_cart->paymentCurrency ?: Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

            if (Plugin::getInstance()->getSettings()->autoSetNewCartAddresses) {
                if (!$this->_cart->shippingAddressId && ($this->_cart->customer && $this->_cart->customer->lastUsedShippingAddressId)) {
                    $address = Plugin::getInstance()->getAddresses()->getAddressById($this->_cart->customer->lastUsedShippingAddressId);
                    $this->_cart->setShippingAddress($address);
                }

                if (!$this->_cart->billingAddressId && ($this->_cart->customer && $this->_cart->customer->lastUsedBillingAddressId)) {
                    $address = Plugin::getInstance()->getAddresses()->getAddressById($this->_cart->customer->lastUsedBillingAddressId);
                    $this->_cart->setBillingAddress($address);
                }
            }

            // Update the cart if the customer has changed and recalculate the cart.
            $customer = Plugin::getInstance()->getCustomers()->getCustomer();
            if ($customer && (!$this->_cart->customerId || $this->_cart->customerId != $customer->id)) {
                $this->_cart->customerId = $customer->id;
                $this->_cart->email = $customer->email;
                $this->_cart->billingAddressId = null;
                $this->_cart->shippingAddressId = null;
                Craft::$app->getElements()->saveElement($this->_cart);
            }
        }

        return $this->_cart;
    }

    /**
     * Forgets a Cart by deleting its cookie.
     *
     * @return void
     */
    public function forgetCart()
    {
        $this->_cart = null;
        $session = Craft::$app->getSession();
        $session->remove($this->cartName);
    }

    /**
     * Removes a line item from the cart.
     *
     * @param Order $cart
     * @param int $lineItemId
     * @return bool
     */
    public function removeFromCart(Order $cart, $lineItemId): bool
    {
        /** @var LineItem $lineItem */
        $lineItem = Plugin::getInstance()->getLineItems()->getLineItemById($lineItemId);

        // Fail if the line item does not belong to the cart.
        if (!$lineItem || ($cart->id != $lineItem->orderId)) {
            return false;
        }

        // Raise the 'beforeRemoveFromCart' event
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
            Plugin::getInstance()->getLineItems()->deleteLineItemById($lineItem->id);
            Craft::$app->getElements()->saveElement($cart);

            // Raise the 'afterRemoveFromCart' event
            if ($this->hasEventHandlers(self::EVENT_AFTER_REMOVE_FROM_CART)) {
                $this->trigger(self::EVENT_AFTER_REMOVE_FROM_CART, new CartEvent([
                    'lineItem' => $lineItem,
                    'order' => $cart
                ]));
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            Craft::error($e->getMessage(), 'commerce');

            return false;
        }

        $transaction->commit();

        return true;
    }

    /**
     * Removes all items from a cart.
     *
     * @param Order $cart
     * @throws \Exception
     */
    public function clearCart(Order $cart)
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            Plugin::getInstance()->getLineItems()->deleteAllLineItemsByOrderId($cart->id);
            Craft::$app->getElements()->saveElement($cart);
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }

        $transaction->commit();
    }

    /**
     * Removes all carts that are incomplete and older than the config setting.
     *
     * @return int The number of carts purged from the database
     */
    public function purgeIncompleteCarts(): int
    {
        $doPurge = Plugin::getInstance()->getSettings()->purgeInactiveCarts;

        if ($doPurge) {
            $cartIds = $this->_getCartsIdsToPurge();
            foreach ($cartIds as $id) {
                Craft::$app->getElements()->deleteElementById($id);
            }

            return count($cartIds);
        }

        return 0;
    }

    /**
     * @return string
     */
    public function generateCartNumber(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    // Private Methods
    // =========================================================================

    /**
     * @return mixed|string
     */
    private function _getSessionCartNumber()
    {
        $session = Craft::$app->getSession();
        $cartNumber = $session[$this->cartName];

        if (!$cartNumber) {
            $cartNumber = $this->generateCartNumber();
            $session->set($this->cartName, $cartNumber);
        }

        return $cartNumber;
    }

    /**
     * Which Carts IDs need to be deleted
     *
     * @return int[]
     */
    private function _getCartsIdsToPurge(): array
    {
        $configInterval = Plugin::getInstance()->getSettings()->purgeInactiveCartsDuration;
        $edge = new \DateTime();
        $interval = new \DateInterval($configInterval);
        $interval->invert = 1;
        $edge->add($interval);

        return (new Query())
            ->select(['orders.id'])
            ->where(['not', ['isCompleted' => 1]])
            ->andWhere('[[orders.dateUpdated]] <= :edge', ['edge' => $edge->format('Y-m-d H:i:s')])
            ->from(['orders' => '{{%commerce_orders}}'])
            ->column();
    }
}
